/**
 * Polling Manager for WebRTC Signaling
 * Polls the backend API for new signaling messages and handles WebRTC communication
 */

export interface PollingMessage {
  type: string;
  userId?: string;
  targetUserId?: string;
  data?: any;
  offer?: RTCSessionDescriptionInit;
  answer?: RTCSessionDescriptionInit;
  candidate?: RTCIceCandidateInit;
  timestamp?: number;
}

export class PollingManager {
  private userId: string = '';
  private sessionId: string = '';
  private pollingInterval: number = 1000; // Poll every 1 second
  private pollTimer: NodeJS.Timeout | null = null;
  private lastCheck: number = 0;
  private eventHandlers: Map<string, Array<(data: any) => void>> = new Map();
  private isConnected: boolean = false;
  private messageQueue: PollingMessage[] = [];
  private reconnectAttempts: number = 0;
  private maxReconnectAttempts: number = 5;
  private reconnectDelay: number = 2000; // Start with 2 seconds

  constructor() {
    // Initialize event handlers map
    this.eventHandlers.set('webrtc-offer', []);
    this.eventHandlers.set('webrtc-answer', []);
    this.eventHandlers.set('webrtc-ice-candidate', []);
    this.eventHandlers.set('whiteboard-operation', []);
    this.eventHandlers.set('cursor-move', []);
    this.eventHandlers.set('user-joined', []);
    this.eventHandlers.set('user-left', []);
  }

  /**
   * Connect to polling service
   */
  async connect(
    userId: string,
    sessionId: string,
    userRole: string,
    userName: string,
    onConnect?: () => void
  ): Promise<void> {
    this.userId = userId;
    this.sessionId = sessionId;
    this.isConnected = true;
    this.lastCheck = Date.now();

    // Re-initialize event handlers if they were cleared (e.g., after disconnect)
    if (this.eventHandlers.size === 0) {
      this.eventHandlers.set('webrtc-offer', []);
      this.eventHandlers.set('webrtc-answer', []);
      this.eventHandlers.set('webrtc-ice-candidate', []);
      this.eventHandlers.set('whiteboard-operation', []);
      this.eventHandlers.set('cursor-move', []);
      this.eventHandlers.set('user-joined', []);
      this.eventHandlers.set('user-left', []);
    }

    // Start polling
    this.startPolling();

    // Process any queued messages
    this.processMessageQueue();

    if (onConnect) {
      onConnect();
    }

    return Promise.resolve();
  }

  /**
   * Start polling for messages
   */
  private startPolling(): void {
    if (this.pollTimer) {
      clearInterval(this.pollTimer);
    }

    this.pollTimer = setInterval(() => {
      this.poll();
    }, this.pollingInterval);
  }

  /**
   * Poll for new messages
   */
  private async poll(): Promise<void> {
    if (!this.isConnected || !this.sessionId) {
      return;
    }

    try {
      const response = await fetch(
        `/api/polling.php?sessionId=${encodeURIComponent(this.sessionId)}&lastCheck=${this.lastCheck}`
      );

      if (!response.ok) {
        if (response.status === 403 || response.status === 404) {
          // Session invalid, stop polling
          this.disconnect();
          return;
        }
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();

      if (data.success && data.messages) {
        this.lastCheck = data.timestamp || Date.now();
        this.processMessages(data.messages);
        this.reconnectAttempts = 0; // Reset on successful poll
      }
    } catch (error) {
      console.error('Polling error:', error);
      this.handlePollingError();
    }
  }

  /**
   * Process incoming messages
   */
  private processMessages(messages: any[]): void {
    for (const message of messages) {
      const messageType = message.type || message.messageType;

      // Handle WebRTC messages
      if (messageType === 'webrtc-offer') {
        this.emit('webrtc-offer', {
          userId: message.userId || message.fromUserId,
          offer: message.data?.offer || message.offer
        });
      } else if (messageType === 'webrtc-answer') {
        this.emit('webrtc-answer', {
          userId: message.userId || message.fromUserId,
          answer: message.data?.answer || message.answer
        });
      } else if (messageType === 'webrtc-ice-candidate') {
        this.emit('webrtc-ice-candidate', {
          userId: message.userId || message.fromUserId,
          candidate: message.data?.candidate || message.candidate
        });
      } else if (messageType === 'whiteboard-operation') {
        this.emit('whiteboard-operation', {
          userId: message.userId,
          operation: message.operation || message.data
        });
      } else if (messageType === 'cursor-move') {
        this.emit('cursor-move', {
          userId: message.userId,
          x: message.x,
          y: message.y
        });
      } else if (messageType === 'user-joined') {
        this.emit('user-joined', {
          userId: message.userId,
          userName: message.userName,
          userRole: message.userRole
        });
      } else if (messageType === 'user-left') {
        this.emit('user-left', {
          userId: message.userId
        });
      }
    }
  }

  /**
   * Send a message via polling API
   */
  async send(message: PollingMessage): Promise<void> {
    if (!this.isConnected || !this.sessionId) {
      // Queue message for later
      this.messageQueue.push(message);
      return;
    }

    try {
      // Determine message type and format
      let action = '';
      let payload: any = {
        sessionId: this.sessionId,
        action: ''
      };

      if (message.type === 'webrtc-offer' || message.type === 'webrtc-answer' || message.type === 'webrtc-ice-candidate') {
        // Validate targetUserId is provided
        if (!message.targetUserId) {
          console.error(`Cannot send ${message.type}: targetUserId is required`);
          throw new Error(`targetUserId is required for ${message.type}`);
        }

        // Send WebRTC signaling via polling.php POST endpoint
        // The backend will handle storing in signaling_queue table
        const response = await fetch('/api/polling.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            sessionId: this.sessionId,
            action: 'signaling',
            toUserId: message.targetUserId,
            messageType: message.type,
            messageData: message.offer || message.answer || message.candidate
          })
        });

        if (!response.ok) {
          throw new Error(`Failed to send ${message.type}`);
        }
      } else if (message.type === 'whiteboard-operation' || message.type === 'cursor-move') {
        // Send whiteboard operations
        const response = await fetch('/api/polling.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            sessionId: this.sessionId,
            action: message.type,
            operation: message.data || message
          })
        });

        if (!response.ok) {
          throw new Error(`Failed to send ${message.type}`);
        }
      }
    } catch (error) {
      console.error('Error sending message:', error);
      // Queue message for retry
      this.messageQueue.push(message);
    }
  }

  /**
   * Process queued messages
   */
  private processMessageQueue(): void {
    if (this.messageQueue.length === 0) {
      return;
    }

    const messages = [...this.messageQueue];
    this.messageQueue = [];

    for (const message of messages) {
      this.send(message).catch(error => {
        console.error('Error processing queued message:', error);
        // Re-queue if still not connected
        if (!this.isConnected) {
          this.messageQueue.push(message);
        }
      });
    }
  }

  /**
   * Register event handler
   */
  on(event: string, handler: (data: any) => void): void {
    if (!this.eventHandlers.has(event)) {
      this.eventHandlers.set(event, []);
    }
    this.eventHandlers.get(event)!.push(handler);
  }

  /**
   * Remove event handler
   */
  off(event: string, handler: (data: any) => void): void {
    const handlers = this.eventHandlers.get(event);
    if (handlers) {
      const index = handlers.indexOf(handler);
      if (index > -1) {
        handlers.splice(index, 1);
      }
    }
  }

  /**
   * Emit event to handlers
   */
  private emit(event: string, data: any): void {
    const handlers = this.eventHandlers.get(event);
    if (handlers) {
      handlers.forEach(handler => {
        try {
          handler(data);
        } catch (error) {
          console.error(`Error in event handler for ${event}:`, error);
        }
      });
    }
  }

  /**
   * Handle polling errors with exponential backoff
   */
  private handlePollingError(): void {
    this.reconnectAttempts++;

    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('Max reconnect attempts reached. Stopping polling.');
      this.disconnect();
      return;
    }

    // Exponential backoff
    const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);
    
    setTimeout(() => {
      if (this.isConnected) {
        this.startPolling();
      }
    }, delay);
  }

  /**
   * Disconnect from polling service
   */
  disconnect(): void {
    this.isConnected = false;

    if (this.pollTimer) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }

    // Clear event handlers
    this.eventHandlers.clear();

    // Clear message queue
    this.messageQueue = [];
  }
}
