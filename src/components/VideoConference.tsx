import React, { useEffect, useRef, useState, useCallback } from 'react';
import { WebRTCManager } from '../utils/webrtc';
import { PollingManager } from '../utils/polling';

interface VideoConferenceProps {
  userId: string;
  userRole: string;
  sessionId: string;
}

interface VideoTile {
  userId: string;
  userName: string;
  userRole: string;
  stream: MediaStream | null;
  element: HTMLVideoElement | null;
  pinned: boolean;
  muted: boolean;
  videoEnabled: boolean;
}

const VideoConference: React.FC<VideoConferenceProps> = ({ userId, userRole, sessionId }) => {
  const localVideoRef = useRef<HTMLVideoElement>(null);
  const remoteVideosRef = useRef<Map<string, HTMLVideoElement>>(new Map());
  const webrtcManagerRef = useRef<WebRTCManager | null>(null);
  const pollingRef = useRef<PollingManager | null>(null);
  const [videoTiles, setVideoTiles] = useState<Map<string, VideoTile>>(new Map());
  const [localMuted, setLocalMuted] = useState(false);
  const [localVideoEnabled, setLocalVideoEnabled] = useState(true);
  const [isScreenSharing, setIsScreenSharing] = useState(false);

  useEffect(() => {
    // Initialize WebRTC and Polling
    const webrtc = new WebRTCManager();
    const polling = new PollingManager();

    webrtcManagerRef.current = webrtc;
    pollingRef.current = polling;

    // Set up WebRTC callbacks
    webrtc.setOnRemoteStream((remoteUserId, stream) => {
      updateRemoteVideo(remoteUserId, stream);
    });

    webrtc.setOnIceCandidate((remoteUserId, candidate) => {
      polling.send({
        type: 'webrtc-ice-candidate',
        targetUserId: remoteUserId,
        candidate: candidate.toJSON()
      });
    });

    // Initialize local stream
    if (localVideoRef.current) {
      webrtc.initializeLocalStream(localVideoRef.current)
        .then(() => {
          // Add local tile
          const localTile: VideoTile = {
            userId,
            userName: 'You',
            userRole,
            stream: null,
            element: localVideoRef.current,
            pinned: userRole === 'teacher',
            muted: false,
            videoEnabled: true
          };
          setVideoTiles(prev => new Map(prev).set(userId, localTile));
        })
        .catch(error => {
          console.error('Error initializing local stream:', error);
        });
    }

    // Connect Polling
    polling.connect(userId, sessionId, userRole, 'User', undefined)
      .then(() => {
        // Set up polling listeners
        polling.on('webrtc-offer', handleWebRTCOffer);
        polling.on('webrtc-answer', handleWebRTCAnswer);
        polling.on('webrtc-ice-candidate', handleWebRTCIceCandidate);
        polling.on('user-joined', handleUserJoined);
        polling.on('user-left', handleUserLeft);
        
        // Check for other participant and establish connection after a short delay
        // This allows the local stream to initialize first
        setTimeout(() => {
          checkAndConnectToOtherParticipant();
        }, 1000);
      })
      .catch(error => {
        console.error('Error connecting polling:', error);
      });

    return () => {
      webrtc.stopLocalStream();
      webrtc.closeAllConnections();
      polling.disconnect();
    };
  }, [userId, userRole, sessionId]);

  const updateRemoteVideo = useCallback((remoteUserId: string, stream: MediaStream) => {
    setVideoTiles(prev => {
      const newTiles = new Map(prev);
      const tile = newTiles.get(remoteUserId);
      if (tile) {
        const newTile = { ...tile, stream };
        newTiles.set(remoteUserId, newTile);
        
        // Update video element
        const videoElement = remoteVideosRef.current.get(remoteUserId);
        if (videoElement) {
          videoElement.srcObject = stream;
        }
      }
      return newTiles;
    });
  }, []);

  const handleWebRTCOffer = useCallback(async (data: any) => {
    const { userId: remoteUserId, offer } = data;
    if (!webrtcManagerRef.current || !offer) {
      console.warn('Cannot handle offer: missing manager or offer data', { remoteUserId, hasOffer: !!offer });
      return;
    }

    try {
      console.log('Handling WebRTC offer from user:', remoteUserId);
      const answer = await webrtcManagerRef.current.handleOffer(remoteUserId, offer);
      if (answer && pollingRef.current) {
        pollingRef.current.send({
          type: 'webrtc-answer',
          targetUserId: remoteUserId,
          answer: answer
        });
        console.log('Sent WebRTC answer to user:', remoteUserId);
      }
    } catch (error) {
      console.error('Error handling offer:', error);
    }
  }, []);

  const handleWebRTCAnswer = useCallback(async (data: any) => {
    const { userId: remoteUserId, answer } = data;
    if (!webrtcManagerRef.current || !answer) {
      console.warn('Cannot handle answer: missing manager or answer data', { remoteUserId, hasAnswer: !!answer });
      return;
    }

    try {
      console.log('Handling WebRTC answer from user:', remoteUserId);
      await webrtcManagerRef.current.handleAnswer(remoteUserId, answer);
    } catch (error) {
      console.error('Error handling answer:', error);
    }
  }, []);

  const handleWebRTCIceCandidate = useCallback(async (data: any) => {
    const { userId: remoteUserId, candidate } = data;
    if (!webrtcManagerRef.current || !candidate) {
      console.warn('Cannot handle ICE candidate: missing manager or candidate data', { remoteUserId, hasCandidate: !!candidate });
      return;
    }

    try {
      await webrtcManagerRef.current.handleIceCandidate(remoteUserId, candidate);
    } catch (error) {
      console.error('Error handling ICE candidate:', error);
    }
  }, []);

  const handleUserJoined = useCallback(async (data: any) => {
    const { userId: remoteUserId, userName, userRole: remoteRole } = data;
    
    // Create peer connection
    if (webrtcManagerRef.current) {
      const pc = await webrtcManagerRef.current.createPeerConnection(remoteUserId, (stream) => {
        updateRemoteVideo(remoteUserId, stream);
      });

      // Create offer if we're the teacher or first to join
      if (userRole === 'teacher' || videoTiles.size === 1) {
        const offer = await webrtcManagerRef.current.createOffer(remoteUserId);
        pollingRef.current?.send({
          type: 'webrtc-offer',
          targetUserId: remoteUserId,
          offer: offer
        });
      }

      // Add remote tile
      const remoteTile: VideoTile = {
        userId: remoteUserId,
        userName,
        userRole: remoteRole,
        stream: null,
        element: null,
        pinned: remoteRole === 'teacher',
        muted: false,
        videoEnabled: true
      };
      setVideoTiles(prev => new Map(prev).set(remoteUserId, remoteTile));
    }
  }, [userRole, videoTiles.size, updateRemoteVideo]);

  const handleUserLeft = useCallback((data: any) => {
    const { userId: remoteUserId } = data;
    if (webrtcManagerRef.current) {
      webrtcManagerRef.current.closeConnection(remoteUserId);
    }
    setVideoTiles(prev => {
      const newTiles = new Map(prev);
      newTiles.delete(remoteUserId);
      return newTiles;
    });
    remoteVideosRef.current.delete(remoteUserId);
  }, []);

  const checkAndConnectToOtherParticipant = useCallback(async () => {
    // Get session info to find the other participant
    try {
      const response = await fetch(`/api/sessions.php?action=active&sessionId=${sessionId}`);
      if (response.ok) {
        const data = await response.json();
        if (data.session) {
          const otherUserId = String(data.session.teacher_id == userId 
            ? data.session.student_id 
            : data.session.teacher_id);
          
          // Check if we already have this user
          setVideoTiles(prev => {
            if (prev.has(otherUserId)) {
              return prev; // Already connected
            }
            
            // Create peer connection if webrtc manager is ready
            if (webrtcManagerRef.current) {
              webrtcManagerRef.current.createPeerConnection(otherUserId, (stream) => {
                updateRemoteVideo(otherUserId, stream);
              }).then(() => {
                // Teacher initiates the connection
                if (userRole === 'teacher') {
                  webrtcManagerRef.current?.createOffer(otherUserId).then(offer => {
                    pollingRef.current?.send({
                      type: 'webrtc-offer',
                      targetUserId: otherUserId,
                      offer: offer
                    });
                  });
                }
              });
            }

            // Add remote tile placeholder
            const remoteTile: VideoTile = {
              userId: otherUserId,
              userName: 'Participant',
              userRole: data.session.teacher_id == userId ? 'student' : 'teacher',
              stream: null,
              element: null,
              pinned: data.session.teacher_id != userId,
              muted: false,
              videoEnabled: true
            };
            
            return new Map(prev).set(otherUserId, remoteTile);
          });
        }
      }
    } catch (error) {
      console.error('Error checking for other participant:', error);
    }
  }, [userId, userRole, sessionId, updateRemoteVideo]);

  const toggleMute = () => {
    const newMuted = !localMuted;
    setLocalMuted(newMuted);
    webrtcManagerRef.current?.toggleAudio(!newMuted);
  };

  const toggleVideo = () => {
    const newEnabled = !localVideoEnabled;
    setLocalVideoEnabled(newEnabled);
    webrtcManagerRef.current?.toggleVideo(newEnabled);
  };

  const togglePin = (tileUserId: string) => {
    setVideoTiles(prev => {
      const newTiles = new Map(prev);
      const tile = newTiles.get(tileUserId);
      if (tile) {
        newTiles.set(tileUserId, { ...tile, pinned: !tile.pinned });
      }
      return newTiles;
    });
  };

  const handleScreenShare = async () => {
    if (!webrtcManagerRef.current) return;

    try {
      if (isScreenSharing) {
        await webrtcManagerRef.current.stopScreenShare();
        setIsScreenSharing(false);
      } else {
        await webrtcManagerRef.current.startScreenShare();
        setIsScreenSharing(true);
      }
    } catch (error) {
      console.error('Error toggling screen share:', error);
    }
  };

  // Sort tiles: pinned first, then teacher, then others
  const sortedTiles = Array.from(videoTiles.values()).sort((a, b) => {
    if (a.pinned && !b.pinned) return -1;
    if (!a.pinned && b.pinned) return 1;
    if (a.userRole === 'teacher' && b.userRole !== 'teacher') return -1;
    if (a.userRole !== 'teacher' && b.userRole === 'teacher') return 1;
    return 0;
  });

  return (
    <div className="video-tiles-container">
      {sortedTiles.map(tile => {
        const isLocal = tile.userId === userId;
        const videoRef = isLocal ? localVideoRef : (() => {
          if (!remoteVideosRef.current.has(tile.userId)) {
            const video = document.createElement('video');
            video.autoplay = true;
            video.playsInline = true;
            remoteVideosRef.current.set(tile.userId, video);
          }
          return remoteVideosRef.current.get(tile.userId)!;
        })();

        return (
          <div
            key={tile.userId}
            className={`video-tile ${tile.userRole === 'teacher' ? 'teacher' : ''} ${tile.pinned ? 'pinned' : ''}`}
          >
            <video
              ref={isLocal ? localVideoRef : undefined}
              autoPlay
              playsInline
              muted={isLocal}
              style={{ display: tile.videoEnabled ? 'block' : 'none' }}
            />
            {!tile.videoEnabled && (
              <div style={{
                position: 'absolute',
                top: '50%',
                left: '50%',
                transform: 'translate(-50%, -50%)',
                color: 'white',
                fontSize: '1.2rem'
              }}>
                {tile.userName}
              </div>
            )}
            <div className="video-tile-overlay">
              <span className="video-tile-name">
                {tile.userName}
                {tile.userRole === 'teacher' && (
                  <span className="teacher-badge" title="Teacher">
                    <i className="fas fa-chalkboard-teacher"></i>
                  </span>
                )}
              </span>
              <div className="video-tile-controls">
                <button
                  className="video-tile-btn"
                  onClick={() => togglePin(tile.userId)}
                  title={tile.pinned ? 'Unpin' : 'Pin'}
                >
                  <i className={`fas fa-${tile.pinned ? 'thumbtack' : 'thumbtack'}`}></i>
                </button>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
};

export default VideoConference;

