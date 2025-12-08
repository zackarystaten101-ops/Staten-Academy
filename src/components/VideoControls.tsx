import React, { useState, useRef, useEffect } from 'react';

interface VideoControlsProps {
  onVocabularyToggle: () => void;
  vocabularyOpen: boolean;
  darkMode: boolean;
  onThemeToggle: () => void;
  onMute?: () => void;
  onVideoToggle?: () => void;
  onScreenShare?: () => void;
  onLeave?: () => void;
  onSettings?: () => void;
  isTeacher?: boolean;
}

const VideoControls: React.FC<VideoControlsProps> = ({
  onVocabularyToggle,
  vocabularyOpen,
  darkMode,
  onThemeToggle,
  onMute,
  onVideoToggle,
  onScreenShare,
  onLeave,
  onSettings,
  isTeacher = false
}) => {
  const [muted, setMuted] = useState(false);
  const [videoEnabled, setVideoEnabled] = useState(true);
  const [screenSharing, setScreenSharing] = useState(false);
  const [showSettings, setShowSettings] = useState(false);
  const [showLeaveConfirm, setShowLeaveConfirm] = useState(false);
  const [audioDevices, setAudioDevices] = useState<MediaDeviceInfo[]>([]);
  const [videoDevices, setVideoDevices] = useState<MediaDeviceInfo[]>([]);
  const [selectedAudio, setSelectedAudio] = useState<string>('');
  const [selectedVideo, setSelectedVideo] = useState<string>('');

  useEffect(() => {
    loadDevices();
  }, []);

  const loadDevices = async () => {
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      setAudioDevices(devices.filter(d => d.kind === 'audioinput'));
      setVideoDevices(devices.filter(d => d.kind === 'videoinput'));
    } catch (error) {
      console.error('Error loading devices:', error);
    }
  };

  const handleMute = () => {
    const newMuted = !muted;
    setMuted(newMuted);
    onMute?.();
  };

  const handleVideoToggle = () => {
    const newEnabled = !videoEnabled;
    setVideoEnabled(newEnabled);
    onVideoToggle?.();
  };

  const handleScreenShare = () => {
    const newSharing = !screenSharing;
    setScreenSharing(newSharing);
    onScreenShare?.();
  };

  const handleLeave = () => {
    if (showLeaveConfirm) {
      onLeave?.();
    } else {
      setShowLeaveConfirm(true);
    }
  };

  const cancelLeave = () => {
    setShowLeaveConfirm(false);
  };

  return (
    <>
      <div className="video-controls-bar">
        <button
          className={`control-button ${muted ? 'active' : ''}`}
          onClick={handleMute}
          title={muted ? 'Unmute' : 'Mute'}
        >
          <i className={`fas fa-microphone${muted ? '-slash' : ''}`}></i>
        </button>

        <button
          className={`control-button ${!videoEnabled ? 'active' : ''}`}
          onClick={handleVideoToggle}
          title={videoEnabled ? 'Turn off camera' : 'Turn on camera'}
        >
          <i className={`fas fa-video${videoEnabled ? '' : '-slash'}`}></i>
        </button>

        <button
          className={`control-button ${screenSharing ? 'active' : ''}`}
          onClick={handleScreenShare}
          title={screenSharing ? 'Stop sharing' : 'Share screen'}
        >
          <i className="fas fa-desktop"></i>
        </button>

        <div className="control-divider"></div>

        <button
          className="control-button"
          onClick={() => setShowSettings(!showSettings)}
          title="Settings"
        >
          <i className="fas fa-cog"></i>
        </button>

        {isTeacher && (
          <button
            className="control-button"
            onClick={onVocabularyToggle}
            title={vocabularyOpen ? 'Hide vocabulary' : 'Show vocabulary'}
          >
            <i className="fas fa-book"></i>
            {vocabularyOpen && <span className="control-badge"></span>}
          </button>
        )}

        <button
          className="control-button"
          onClick={onThemeToggle}
          title={darkMode ? 'Light mode' : 'Dark mode'}
        >
          <i className={`fas fa-${darkMode ? 'sun' : 'moon'}`}></i>
        </button>

        <div className="control-divider"></div>

        <button
          className="control-button danger"
          onClick={handleLeave}
          title="Leave classroom"
        >
          <i className="fas fa-times"></i>
        </button>
      </div>

      {showSettings && (
        <div className="settings-modal">
          <div className="settings-content">
            <div className="settings-header">
              <h3>Settings</h3>
              <button className="settings-close" onClick={() => setShowSettings(false)}>
                <i className="fas fa-times"></i>
              </button>
            </div>
            <div className="settings-body">
              <div className="form-group">
                <label>Microphone</label>
                <select
                  value={selectedAudio}
                  onChange={(e) => setSelectedAudio(e.target.value)}
                >
                  <option value="">Default</option>
                  {audioDevices.map(device => (
                    <option key={device.deviceId} value={device.deviceId}>
                      {device.label || `Microphone ${device.deviceId.slice(0, 8)}`}
                    </option>
                  ))}
                </select>
              </div>
              <div className="form-group">
                <label>Camera</label>
                <select
                  value={selectedVideo}
                  onChange={(e) => setSelectedVideo(e.target.value)}
                >
                  <option value="">Default</option>
                  {videoDevices.map(device => (
                    <option key={device.deviceId} value={device.deviceId}>
                      {device.label || `Camera ${device.deviceId.slice(0, 8)}`}
                    </option>
                  ))}
                </select>
              </div>
              <div className="form-group">
                <label>Video Quality</label>
                <select defaultValue="auto">
                  <option value="auto">Auto</option>
                  <option value="high">High (720p)</option>
                  <option value="medium">Medium (480p)</option>
                  <option value="low">Low (360p)</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      )}

      {showLeaveConfirm && (
        <div className="modal-overlay" onClick={cancelLeave}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3>Leave Classroom?</h3>
            <p>Are you sure you want to leave? Your progress will be saved.</p>
            <div className="modal-actions">
              <button className="btn-secondary" onClick={cancelLeave}>
                Cancel
              </button>
              <button className="btn-primary danger" onClick={handleLeave}>
                Leave
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};

export default VideoControls;

