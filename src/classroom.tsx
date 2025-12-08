import React from 'react';
import ReactDOM from 'react-dom/client';
import ClassroomLayout from './components/ClassroomLayout';
import './styles/classroom.css';

// Get initial data from PHP via data attributes
const rootElement = document.getElementById('classroom-root');
if (rootElement) {
  const initialData = {
    userId: rootElement.dataset.userId || '',
    userRole: rootElement.dataset.userRole || '',
    userName: rootElement.dataset.userName || '',
    sessionId: rootElement.dataset.sessionId || '',
    lessonId: rootElement.dataset.lessonId || '',
    teacherId: rootElement.dataset.teacherId || '',
    studentId: rootElement.dataset.studentId || ''
  };

  // If lessonId is provided but no sessionId, get or create session
  if (initialData.lessonId && !initialData.sessionId) {
    fetch(`/api/sessions.php?action=get-or-create&lessonId=${initialData.lessonId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success && data.session) {
          // Update sessionId and render
          initialData.sessionId = data.session.session_id;
          renderClassroom(initialData);
        } else {
          console.error('Failed to get or create session:', data.error);
          renderClassroom(initialData);
        }
      })
      .catch(error => {
        console.error('Error getting session:', error);
        renderClassroom(initialData);
      });
  } else {
    renderClassroom(initialData);
  }
}

function renderClassroom(initialData: any) {
  const rootElement = document.getElementById('classroom-root');
  if (!rootElement) return;
  
  const root = ReactDOM.createRoot(rootElement);
  root.render(
    <React.StrictMode>
      <ClassroomLayout initialData={initialData} />
    </React.StrictMode>
  );
}

