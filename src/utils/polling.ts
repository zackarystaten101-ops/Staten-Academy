export interface PollingMessage {
  type: string;
  [key: string]: any;
}

export class PollingManager {
  private pollingInterval: number = 2000; // 2 seconds for better real-time performance
  private pollingTimer: NodeJS.Timeout | null = null;
  private url: string;
  private userId: string = '';
  private sessionId: string = '';
  private userRole: string = '';
  private userName: string = '';
  private lastCheck: number = 0;
  private listeners: Map<string, Set<(data: any) => void>> = new Map();
  private messageQueue: PollingMessage[] = [];
  private isConnected: boolean = false;
  private reconnectAttempts: number = 0;
  private maxReconnectAttempts: number = 5;
  private sendQueue: Array<{ type: string; data: any }> = [];
  private isPolling: boolean = false; // Prevent concurrent polls

  constructor(url: string = '/api/polling.php') {
    this.url = url;
  }

  connect(userId: string, sessionId: string, userRole: string, userName: string, token?: string): Promise<void> {
    return new Promise((resolve, reject) => {
      this.userId = userId;
      this.sessionId = sessionId;
      this.userRole = userRole;
      this.userName = userName;
      this.lastCheck = Date.now();
      this.isConnected = true;
      this.reconnectAttempts = 0;

      // Start polling
      this.startPolling();
      
      // Process any queued messages
      this.processSendQueue();

      resolve();
    });
  }

  private startPolling() {
    if (this.pollingTimer) {
      clearInterval(this.pollingTimer);
    }

    this.pollingTimer = setInterval(() => {
      this.poll();
    }, this.pollingInterval);
  }

  private async poll() {
    if (!this.isConnected || !this.sessionId || !this.userId || this.isPolling) {
      return;
    }

    this.isPolling = true;

    try {
      const url = `${this.url}?sessionId=${encodeURIComponent(this.sessionId)}&userId=${encodeURIComponent(this.userId)}&lastCheck=${this.lastCheck}`;
      const response = await fetch(url, {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) {
        if (response.status === 401 || response.status === 403) {
          this.isConnected = false;
          this.handleDisconnect();
          this.isPolling = false;
          return;
        }
        throw new Error(`Polling failed: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success && data.messages) {
        this.lastCheck = data.timestamp || Date.now();
        
        // Process messages immediately
        if (data.messages.length > 0) {
          data.messages.forEach((message: any) => {
            this.handleMessage(message);
          });
        }
      }

      this.reconnectAttempts = 0; // Reset on successful poll
    } catch (error) {
      console.error('Polling error:', error);
      this.reconnectAttempts++;
      
      if (this.reconnectAttempts >= this.maxReconnectAttempts) {
        this.isConnected = false;
        this.handleDisconnect();
      }
    } finally {
      this.isPolling = false;
    }
  }

  private handleMessage(message: PollingMessage) {
    // Handle different message types
    if (message.type === 'webrtc-offer') {
      // Extract offer from data - message_data is stored as {"offer": {...}}
      // So message.data will be {offer: {...}}
      const offerData = message.data?.offer || message.data;
      if (offerData && (offerData.type === 'offer' || offerData.sdp)) {
        console.log('Received WebRTC offer from user:', message.userId);
        this.notifyListeners('webrtc-offer', {
          userId: String(message.userId || message.from_user_id || ''),
          offer: offerData
        });
      } else {
        console.warn('Invalid offer data received:', message);
      }
    } else if (message.type === 'webrtc-answer') {
      const answerData = message.data?.answer || message.data;
      if (answerData && (answerData.type === 'answer' || answerData.sdp)) {
        console.log('Received WebRTC answer from user:', message.userId);
        this.notifyListeners('webrtc-answer', {
          userId: String(message.userId || message.from_user_id || ''),
          answer: answerData
        });
      } else {
        console.warn('Invalid answer data received:', message);
      }
    } else if (message.type === 'webrtc-ice-candidate') {
      const candidateData = message.data?.candidate || message.data;
      if (candidateData && (candidateData.candidate || candidateData.sdpMLineIndex !== undefined)) {
        this.notifyListeners('webrtc-ice-candidate', {
          userId: String(message.userId || message.from_user_id || ''),
          candidate: candidateData
        });
      } else {
        console.warn('Invalid ICE candidate data received:', message);
      }
    } else if (message.type === 'whiteboard-operation') {
      this.notifyListeners('whiteboard-operation', {
        userId: message.userId,
        operation: message.operation,
        ...message
      });
    } else if (message.type === 'cursor-move') {
      this.notifyListeners('cursor-move', {
        userId: message.userId,
        userName: message.userName,
        x: message.x,
        y: message.y
      });
    } else if (message.type === 'user-joined') {
      this.notifyListeners('user-joined', message);
    } else if (message.type === 'user-left') {
      this.notifyListeners('user-left', message);
    }

    // Also notify 'message' listeners for all messages
    this.notifyListeners('message', message);
  }

  private notifyListeners(event: string, data: any) {
    const listeners = this.listeners.get(event);
    if (listeners) {
      listeners.forEach(listener => {
        try {
          listener(data);
        } catch (error) {
          console.error('Error in polling listener:', error);
        }
      });
    }
  }

  send(message: PollingMessage) {
    if (!this.isConnected) {
      // Queue message for when connection is established
      this.sendQueue.push({ type: message.type, data: message });
      return;
    }

    // Send immediately via appropriate API endpoint
    this.sendMessage(message);
  }

  private async sendMessage(message: PollingMessage) {
    try {
      if (message.type === 'webrtc-offer') {
        const response = await fetch('/api/webrtc.php?action=offer', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            sessionId: this.sessionId,
            targetUserId: message.targetUserId,
            offer: message.offer
          })
        });
        // Trigger immediate poll after sending critical signaling message
        if (response.ok) {
          setTimeout(() => this.poll(), 100);
        }
      } else if (message.type === 'webrtc-answer') {
        const response = await fetch('/api/webrtc.php?action=answer', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            sessionId: this.sessionId,
            targetUserId: message.targetUserId,
            answer: message.answer
          })
        });
        // Trigger immediate poll after sending critical signaling message
        if (response.ok) {
          setTimeout(() => this.poll(), 100);
        }
      } else if (message.type === 'webrtc-ice-candidate') {
        const response = await fetch('/api/webrtc.php?action=ice-candidate', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            sessionId: this.sessionId,
            targetUserId: message.targetUserId,
            candidate: message.candidate
          })
        });
        // Trigger immediate poll after sending critical signaling message
        if (response.ok) {
          setTimeout(() => this.poll(), 100);
        }
      } else if (message.type === 'whiteboard-operation') {
        // Store whiteboard operation in queue
        await fetch('/api/polling.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'whiteboard-operation',
            sessionId: this.sessionId,
            operation: message.operation
          })
        });
      } else if (message.type === 'cursor-move') {
        // Store cursor move in queue (throttled - don't poll immediately)
        await fetch('/api/polling.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'cursor-move',
            sessionId: this.sessionId,
            x: message.x,
            y: message.y
          })
        });
      }
    } catch (error) {
      console.error('Error sending message:', error);
    }
  }

  private async processSendQueue() {
    while (this.sendQueue.length > 0 && this.isConnected) {
      const message = this.sendQueue.shift();
      if (message) {
        await this.sendMessage(message as PollingMessage);
      }
    }
  }

  on(event: string, callback: (data: any) => void) {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, new Set());
    }
    this.listeners.get(event)!.add(callback);
  }

  off(event: string, callback?: (data: any) => void) {
    if (!this.listeners.has(event)) {
      return;
    }

    if (callback) {
      this.listeners.get(event)!.delete(callback);
    } else {
      this.listeners.delete(event);
    }
  }

  disconnect() {
    this.isConnected = false;
    if (this.pollingTimer) {
      clearInterval(this.pollingTimer);
      this.pollingTimer = null;
    }
    this.listeners.clear();
    this.messageQueue = [];
    this.sendQueue = [];
  }

  isConnected(): boolean {
    return this.isConnected;
  }

  private handleDisconnect() {
    if (this.pollingTimer) {
      clearInterval(this.pollingTimer);
      this.pollingTimer = null;
    }

    // Attempt reconnection with exponential backoff
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
      console.log(`Reconnecting in ${delay}ms... (attempt ${this.reconnectAttempts})`);
      
      setTimeout(() => {
        if (this.userId && this.sessionId) {
          this.connect(this.userId, this.sessionId, this.userRole, this.userName)
            .catch(console.error);
        }
      }, delay);
    }
  }
}

