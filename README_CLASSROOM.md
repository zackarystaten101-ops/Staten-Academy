# Classroom Video Conferencing and Whiteboard System

This document describes the video conferencing and whiteboard system implementation for Staten Academy.

## Setup Instructions

### 1. Install Dependencies

```bash
# Install Node.js dependencies
npm install

# Install PHP dependencies (Composer)
composer install
```

### 2. Database Setup

Run the database schema updates:

```sql
-- The new tables are already added to database-schema.sql
-- Run the SQL file or execute the CREATE TABLE statements for:
-- - video_sessions
-- - whiteboard_states
-- - vocabulary_words
-- - session_vocabulary
```

### 3. Build Frontend

```bash
# Development mode
npm run dev

# Production build
npm run build
```

The build output will be in `public/assets/js/classroom.bundle.js`

### 4. Start WebSocket Server

```bash
# Start the PHP WebSocket server
php websocket/server.php
```

The WebSocket server runs on port 8080 by default.

### 5. Configuration

Update the WebSocket URL in `classroom.php` if your server is not on localhost:

```php
data-websocket-url="ws://your-domain.com:8080"
```

## Architecture

- **Frontend**: React + TypeScript components embedded in PHP pages
- **Real-time**: PHP WebSocket server (Ratchet) for signaling and whiteboard sync
- **Video**: WebRTC peer-to-peer connections
- **Whiteboard**: Fabric.js canvas with real-time collaboration
- **Database**: MySQL for session and vocabulary persistence

## Features

### Video Conferencing
- Real-time audio/video streaming
- Screen sharing
- Device selection (camera, microphone)
- Mute/unmute controls
- Teacher/student video tile layout

### Whiteboard
- Collaborative drawing tools (pen, highlighter, eraser)
- Shapes (rectangle, circle, line)
- Text tool
- Image upload
- Sticky notes
- Pointer/laser tool
- Zoom and pan
- Cursor presence indicators
- Undo/redo
- Autosave every 5 seconds

### Vocabulary
- Word list management
- Add/edit/delete words
- Export to CSV/PDF
- Import from CSV
- Drag vocabulary cards to whiteboard
- Teacher-only lock for vocabulary cards

## API Endpoints

### Sessions
- `POST /api/sessions.php?action=create` - Create new session
- `POST /api/sessions.php?action=join` - Join session
- `GET /api/sessions.php?action=get-state&sessionId=...` - Get whiteboard state
- `POST /api/sessions.php?action=save-state` - Save whiteboard state
- `POST /api/sessions.php?action=end` - End session

### Vocabulary
- `GET /api/vocabulary.php?teacherId=...` - List words
- `POST /api/vocabulary.php` - Create word
- `PUT /api/vocabulary.php?id=...` - Update word
- `DELETE /api/vocabulary.php?id=...` - Delete word
- `GET /api/vocabulary.php?action=export&format=csv&teacherId=...` - Export
- `POST /api/vocabulary.php?action=import` - Import CSV

### WebRTC Signaling
- `POST /api/webrtc.php?action=offer` - Handle WebRTC offer
- `POST /api/webrtc.php?action=answer` - Handle WebRTC answer
- `POST /api/webrtc.php?action=ice-candidate` - Handle ICE candidate

## WebSocket Messages

### Client to Server
- `{ type: 'join', userId, sessionId, userRole, userName }` - Join session
- `{ type: 'webrtc-offer', targetUserId, offer }` - Send WebRTC offer
- `{ type: 'webrtc-answer', targetUserId, answer }` - Send WebRTC answer
- `{ type: 'webrtc-ice-candidate', targetUserId, candidate }` - Send ICE candidate
- `{ type: 'whiteboard-operation', operation }` - Whiteboard operation
- `{ type: 'cursor-move', x, y }` - Cursor position update

### Server to Client
- `{ type: 'joined', sessionId, users }` - Join confirmation
- `{ type: 'user-joined', userId, userName, userRole }` - User joined
- `{ type: 'user-left', userId, userName }` - User left
- `{ type: 'webrtc-offer', userId, offer }` - Receive WebRTC offer
- `{ type: 'webrtc-answer', userId, answer }` - Receive WebRTC answer
- `{ type: 'webrtc-ice-candidate', userId, candidate }` - Receive ICE candidate
- `{ type: 'whiteboard-operation', userId, operation }` - Whiteboard operation
- `{ type: 'cursor-move', userId, userName, x, y }` - Cursor position

## Development

### File Structure
```
src/
  components/
    ClassroomLayout.tsx
    VideoConference.tsx
    VideoControls.tsx
    Whiteboard.tsx
    WhiteboardToolbar.tsx
    VocabularyPanel.tsx
  stores/
    whiteboardStore.ts
  utils/
    webrtc.ts
    websocket.ts
    fabricHelpers.ts
  styles/
    classroom.css
api/
  sessions.php
  vocabulary.php
  webrtc.php
websocket/
  server.php
```

### Running in Development

1. Start Vite dev server: `npm run dev`
2. Start WebSocket server: `php websocket/server.php`
3. Access classroom at: `http://localhost/classroom.php?sessionId=...`

## Production Deployment

1. Build frontend: `npm run build`
2. Copy `public/assets/js/classroom.bundle.js` to production
3. Copy `public/assets/css/classroom.css` to production
4. Set up WebSocket server as a service (systemd, supervisor, etc.)
5. Configure firewall to allow WebSocket connections (port 8080)
6. Update WebSocket URL in `classroom.php` to production domain

## Troubleshooting

### WebSocket Connection Failed
- Check if WebSocket server is running
- Verify firewall allows port 8080
- Check WebSocket URL in `classroom.php`

### Video Not Working
- Ensure HTTPS is used (required for WebRTC in production)
- Check browser permissions for camera/microphone
- Verify STUN servers are accessible

### Whiteboard Not Syncing
- Check WebSocket connection status
- Verify session access permissions
- Check browser console for errors

## Security Notes

- All API endpoints require authentication
- Session access is verified before joining
- Only teachers can create/end sessions
- Vocabulary cards can be locked by teachers
- WebSocket connections verify session membership


