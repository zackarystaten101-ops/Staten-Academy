import React, { useState, useEffect } from 'react';
import VideoConference from './VideoConference';
import Whiteboard from './Whiteboard';
import WhiteboardToolbar from './WhiteboardToolbar';
import VocabularyPanel from './VocabularyPanel';
import VideoControls from './VideoControls';

interface InitialData {
  userId: string;
  userRole: string;
  userName: string;
  sessionId: string;
  lessonId: string;
  teacherId: string;
  studentId: string;
}

interface ClassroomLayoutProps {
  initialData: InitialData;
}

const ClassroomLayout: React.FC<ClassroomLayoutProps> = ({ initialData }) => {
  const [vocabularyPanelOpen, setVocabularyPanelOpen] = useState(false);
  const [darkMode, setDarkMode] = useState(false);
  const isTeacher = initialData.userRole === 'teacher';

  useEffect(() => {
    // Load theme preference from localStorage
    const savedTheme = localStorage.getItem('classroom-theme');
    if (savedTheme === 'dark') {
      setDarkMode(true);
      document.documentElement.classList.add('dark-mode');
    }
  }, []);

  const toggleTheme = () => {
    const newTheme = !darkMode;
    setDarkMode(newTheme);
    if (newTheme) {
      document.documentElement.classList.add('dark-mode');
      localStorage.setItem('classroom-theme', 'dark');
    } else {
      document.documentElement.classList.remove('dark-mode');
      localStorage.setItem('classroom-theme', 'light');
    }
  };

  return (
    <div className={`classroom-layout ${darkMode ? 'dark-mode' : ''}`}>
      <div className="classroom-main-container">
        {/* Video Tiles Section (Top Left) */}
        <div className="video-section">
          <VideoConference
            userId={initialData.userId}
            userRole={initialData.userRole}
            sessionId={initialData.sessionId}
          />
        </div>

        {/* Whiteboard Section (Main Area) */}
        <div className="whiteboard-section">
          <WhiteboardToolbar />
          <Whiteboard
            sessionId={initialData.sessionId}
            userId={initialData.userId}
            userRole={initialData.userRole}
            userName={initialData.userName}
            onVocabularyCardAdded={(word, definition, x, y, locked) => {
              // Save vocabulary card position to session
              fetch('/api/sessions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  action: 'save-vocabulary-position',
                  sessionId: initialData.sessionId,
                  word,
                  definition,
                  x,
                  y,
                  locked
                })
              }).catch(console.error);
            }}
          />
        </div>

        {/* Vocabulary Panel (Right Side, Optional) */}
        {vocabularyPanelOpen && (
          <VocabularyPanel
            teacherId={initialData.teacherId}
            sessionId={initialData.sessionId}
            onClose={() => setVocabularyPanelOpen(false)}
            onAddToBoard={(word, definition) => {
              // Teachers can lock vocabulary cards
              const event = new CustomEvent('addVocabularyCard', {
                detail: { 
                  word, 
                  definition, 
                  locked: isTeacher // Only teachers can lock cards
                }
              });
              window.dispatchEvent(event);
            }}
          />
        )}
      </div>

      {/* Bottom Control Bar */}
      <VideoControls
        onVocabularyToggle={() => setVocabularyPanelOpen(!vocabularyPanelOpen)}
        vocabularyOpen={vocabularyPanelOpen}
        darkMode={darkMode}
        onThemeToggle={toggleTheme}
        isTeacher={isTeacher}
      />
    </div>
  );
};

export default ClassroomLayout;

