export interface RTCConfig {
  iceServers: RTCConfiguration['iceServers'];
}

export class WebRTCManager {
  private localStream: MediaStream | null = null;
  private peerConnections: Map<string, RTCPeerConnection> = new Map();
  private localVideoElement: HTMLVideoElement | null = null;
  private onRemoteStream: ((userId: string, stream: MediaStream) => void) | null = null;
  private onConnectionStateChange: ((userId: string, state: RTCPeerConnectionState) => void) | null = null;

  constructor() {}

  async initializeLocalStream(videoElement: HTMLVideoElement, audioDeviceId?: string, videoDeviceId?: string): Promise<MediaStream> {
    const constraints: MediaStreamConstraints = {
      video: videoDeviceId ? { deviceId: { exact: videoDeviceId } } : true,
      audio: audioDeviceId ? { deviceId: { exact: audioDeviceId } } : true
    };

    try {
      this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
      videoElement.srcObject = this.localStream;
      this.localVideoElement = videoElement;
      return this.localStream;
    } catch (error) {
      console.error('Error accessing media devices:', error);
      throw error;
    }
  }

  async createPeerConnection(userId: string, onRemoteStream: (stream: MediaStream) => void): Promise<RTCPeerConnection> {
    const config: RTCConfiguration = {
      iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' }
      ]
    };

    const pc = new RTCPeerConnection(config);
    this.peerConnections.set(userId, pc);

    // Add local tracks
    if (this.localStream) {
      this.localStream.getTracks().forEach(track => {
        pc.addTrack(track, this.localStream!);
      });
    }

    // Handle remote stream
    pc.ontrack = (event) => {
      onRemoteStream(event.streams[0]);
    };

    // Handle ICE candidates
    pc.onicecandidate = (event) => {
      if (event.candidate) {
        // Send ICE candidate via signaling
        this.onIceCandidate?.(userId, event.candidate);
      }
    };

    // Handle connection state changes
    pc.onconnectionstatechange = () => {
      const state = pc.connectionState;
      this.onConnectionStateChange?.(userId, state);
    };

    return pc;
  }

  async createOffer(userId: string): Promise<RTCSessionDescriptionInit> {
    const pc = this.peerConnections.get(userId);
    if (!pc) {
      throw new Error('Peer connection not found');
    }

    const offer = await pc.createOffer({
      offerToReceiveAudio: true,
      offerToReceiveVideo: true
    });

    await pc.setLocalDescription(offer);
    return offer;
  }

  async handleOffer(userId: string, offer: RTCSessionDescriptionInit): Promise<RTCSessionDescriptionInit> {
    let pc = this.peerConnections.get(userId);
    
    if (!pc) {
      pc = await this.createPeerConnection(userId, (stream) => {
        this.onRemoteStream?.(userId, stream);
      });
    }

    await pc.setRemoteDescription(new RTCSessionDescription(offer));
    const answer = await pc.createAnswer();
    await pc.setLocalDescription(answer);
    return answer;
  }

  async handleAnswer(userId: string, answer: RTCSessionDescriptionInit): Promise<void> {
    const pc = this.peerConnections.get(userId);
    if (!pc) {
      throw new Error('Peer connection not found');
    }

    await pc.setRemoteDescription(new RTCSessionDescription(answer));
  }

  async handleIceCandidate(userId: string, candidate: RTCIceCandidateInit): Promise<void> {
    const pc = this.peerConnections.get(userId);
    if (!pc) {
      throw new Error('Peer connection not found');
    }

    await pc.addIceCandidate(new RTCIceCandidate(candidate));
  }

  setOnIceCandidate(callback: (userId: string, candidate: RTCIceCandidate) => void) {
    this.onIceCandidate = callback;
  }

  setOnRemoteStream(callback: (userId: string, stream: MediaStream) => void) {
    this.onRemoteStream = callback;
  }

  setOnConnectionStateChange(callback: (userId: string, state: RTCPeerConnectionState) => void) {
    this.onConnectionStateChange = callback;
  }

  private onIceCandidate?: (userId: string, candidate: RTCIceCandidate) => void;

  toggleAudio(enabled: boolean) {
    if (this.localStream) {
      this.localStream.getAudioTracks().forEach(track => {
        track.enabled = enabled;
      });
    }
  }

  toggleVideo(enabled: boolean) {
    if (this.localStream) {
      this.localStream.getVideoTracks().forEach(track => {
        track.enabled = enabled;
      });
    }
  }

  async switchCamera(deviceId: string) {
    if (this.localStream && this.localVideoElement) {
      const videoTrack = this.localStream.getVideoTracks()[0];
      if (videoTrack) {
        videoTrack.stop();
        
        const newStream = await navigator.mediaDevices.getUserMedia({
          video: { deviceId: { exact: deviceId } },
          audio: this.localStream.getAudioTracks()[0]?.getSettings().deviceId ? 
            { deviceId: { exact: this.localStream.getAudioTracks()[0].getSettings().deviceId } } : true
        });

        const newVideoTrack = newStream.getVideoTracks()[0];
        if (this.localVideoElement.srcObject) {
          (this.localVideoElement.srcObject as MediaStream).getVideoTracks().forEach(track => track.stop());
          (this.localVideoElement.srcObject as MediaStream).addTrack(newVideoTrack);
        }

        // Update all peer connections
        this.peerConnections.forEach(pc => {
          const sender = pc.getSenders().find(s => s.track?.kind === 'video');
          if (sender && newVideoTrack) {
            sender.replaceTrack(newVideoTrack);
          }
        });

        this.localStream = this.localVideoElement.srcObject as MediaStream;
      }
    }
  }

  async startScreenShare(): Promise<MediaStream> {
    try {
      const screenStream = await navigator.mediaDevices.getDisplayMedia({
        video: true,
        audio: true
      });

      // Replace video track in peer connections
      const videoTrack = screenStream.getVideoTracks()[0];
      this.peerConnections.forEach(pc => {
        const sender = pc.getSenders().find(s => s.track?.kind === 'video');
        if (sender) {
          sender.replaceTrack(videoTrack);
        }
      });

      if (this.localVideoElement) {
        this.localVideoElement.srcObject = screenStream;
      }

      // Handle screen share end
      videoTrack.onended = () => {
        this.stopScreenShare();
      };

      return screenStream;
    } catch (error) {
      console.error('Error starting screen share:', error);
      throw error;
    }
  }

  async stopScreenShare() {
    if (this.localStream && this.localVideoElement) {
      // Get back to camera
      const audioDeviceId = this.localStream.getAudioTracks()[0]?.getSettings().deviceId;
      const newStream = await navigator.mediaDevices.getUserMedia({
        video: true,
        audio: audioDeviceId ? { deviceId: { exact: audioDeviceId } } : true
      });

      const newVideoTrack = newStream.getVideoTracks()[0];
      this.peerConnections.forEach(pc => {
        const sender = pc.getSenders().find(s => s.track?.kind === 'video');
        if (sender) {
          sender.replaceTrack(newVideoTrack);
        }
      });

      if (this.localVideoElement.srcObject) {
        (this.localVideoElement.srcObject as MediaStream).getVideoTracks().forEach(track => track.stop());
        (this.localVideoElement.srcObject as MediaStream).addTrack(newVideoTrack);
      }

      this.localStream = this.localVideoElement.srcObject as MediaStream;
    }
  }

  closeConnection(userId: string) {
    const pc = this.peerConnections.get(userId);
    if (pc) {
      pc.close();
      this.peerConnections.delete(userId);
    }
  }

  closeAllConnections() {
    this.peerConnections.forEach(pc => pc.close());
    this.peerConnections.clear();
  }

  stopLocalStream() {
    if (this.localStream) {
      this.localStream.getTracks().forEach(track => track.stop());
      this.localStream = null;
    }
    if (this.localVideoElement) {
      this.localVideoElement.srcObject = null;
    }
  }

  async getDevices(): Promise<{ audio: MediaDeviceInfo[], video: MediaDeviceInfo[] }> {
    const devices = await navigator.mediaDevices.enumerateDevices();
    return {
      audio: devices.filter(d => d.kind === 'audioinput'),
      video: devices.filter(d => d.kind === 'videoinput')
    };
  }
}











