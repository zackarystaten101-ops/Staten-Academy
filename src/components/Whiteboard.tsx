import React, { useEffect, useRef, useState, useCallback } from 'react';
import { fabric } from 'fabric';
import { PollingManager } from '../utils/polling';
import { useWhiteboardStore } from '../stores/whiteboardStore';
import { createPenPath, createHighlighterPath, createRectangle, createCircle, createLine, createText, createStickyNote, createVocabularyCard } from '../utils/fabricHelpers';

interface WhiteboardProps {
  sessionId: string;
  userId: string;
  userRole: string;
  userName: string;
  onVocabularyCardAdded?: (word: string, definition: string, x: number, y: number, locked: boolean) => void;
}

interface CursorPresence {
  userId: string;
  userName: string;
  x: number;
  y: number;
}

const Whiteboard: React.FC<WhiteboardProps> = ({ sessionId, userId, userRole, userName, onVocabularyCardAdded }) => {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const fabricCanvasRef = useRef<fabric.Canvas | null>(null);
  const pollingRef = useRef<PollingManager | null>(null);
  const isDrawingRef = useRef(false);
  const currentPathRef = useRef<fabric.Path | null>(null);
  const currentPointsRef = useRef<{ x: number; y: number }[]>([]);
  const cursorPresencesRef = useRef<Map<string, HTMLDivElement>>(new Map());
  const [isPanning, setIsPanning] = useState(false);
  const [lastPanPoint, setLastPanPoint] = useState({ x: 0, y: 0 });
  const [cursorPresences, setCursorPresences] = useState<Map<string, CursorPresence>>(new Map());

  const { activeTool, color, thickness, zoom, panX, panY, setZoom, setPan } = useWhiteboardStore();

  useEffect(() => {
    // Listen for vocabulary card additions
    const handleAddVocabularyCard = (e: CustomEvent) => {
      const { word, definition, locked } = e.detail;
      addVocabularyCardToBoard(word, definition, locked);
    };

    window.addEventListener('addVocabularyCard', handleAddVocabularyCard as EventListener);

    return () => {
      window.removeEventListener('addVocabularyCard', handleAddVocabularyCard as EventListener);
    };
  }, []);

  useEffect(() => {
    if (!canvasRef.current) return;

    // Initialize Fabric.js canvas
    const canvas = new fabric.Canvas(canvasRef.current, {
      width: window.innerWidth - 320 - 60, // Account for video panel and toolbar
      height: window.innerHeight - 100,
      backgroundColor: '#f5f5f5'
    });

    fabricCanvasRef.current = canvas;

    // Set up event handlers
    setupCanvasEvents(canvas);

    // Connect Polling
    const polling = new PollingManager();
    pollingRef.current = polling;

    polling.connect(userId, sessionId, userRole, userName, undefined)
      .then(() => {
        polling.on('whiteboard-operation', handleRemoteOperation);
        polling.on('cursor-move', handleCursorMove);
        polling.on('user-joined', handleUserJoined);
        polling.on('user-left', handleUserLeft);
      })
      .catch(error => {
        console.error('Error connecting polling:', error);
      });

    // Load initial state
    loadWhiteboardState();

    // Handle window resize
    const handleResize = () => {
      if (canvas) {
        canvas.setDimensions({
          width: window.innerWidth - 320 - 60,
          height: window.innerHeight - 100
        });
        canvas.renderAll();
      }
    };

    window.addEventListener('resize', handleResize);

    // Autosave
    const autosaveInterval = setInterval(() => {
      saveWhiteboardState();
    }, 5000);

    return () => {
      window.removeEventListener('resize', handleResize);
      clearInterval(autosaveInterval);
      canvas.dispose();
      polling.disconnect();
    };
  }, [sessionId, userId, userRole, userName]);

  const setupCanvasEvents = (canvas: fabric.Canvas) => {
    // Mouse down
    canvas.on('mouse:down', (options) => {
      const pointer = canvas.getPointer(options.e);
      
      if (activeTool === 'select') {
        canvas.setCursor('default');
        return; // Let Fabric handle selection
      }

      if (activeTool === 'pointer') {
        // Pointer/laser tool - temporary highlight
        handlePointerTool(pointer.x, pointer.y);
        return;
      }

      if (activeTool === 'pen' || activeTool === 'highlighter') {
        isDrawingRef.current = true;
        currentPointsRef.current = [{ x: pointer.x, y: pointer.y }];
        canvas.setCursor('crosshair');
      } else if (activeTool === 'rectangle' || activeTool === 'circle' || activeTool === 'line') {
        // Start shape drawing
        handleShapeStart(pointer.x, pointer.y);
        canvas.setCursor('crosshair');
      } else if (activeTool === 'text') {
        handleTextCreation(pointer.x, pointer.y);
        canvas.setCursor('text');
      } else if (activeTool === 'eraser') {
        handleEraser(options);
        canvas.setCursor('grab');
      } else if (activeTool === 'image') {
        handleImageUpload();
      } else if (activeTool === 'sticky-note') {
        handleStickyNoteCreation(pointer.x, pointer.y);
      }
    });

    // Mouse move
    canvas.on('mouse:move', (options) => {
      const pointer = canvas.getPointer(options.e);
      
      // Send cursor position
      sendCursorPosition(pointer.x, pointer.y);

      if (isDrawingRef.current && (activeTool === 'pen' || activeTool === 'highlighter')) {
        currentPointsRef.current.push({ x: pointer.x, y: pointer.y });
        updateDrawingPath();
      } else if (activeTool === 'rectangle' || activeTool === 'circle' || activeTool === 'line') {
        handleShapeUpdate(pointer.x, pointer.y);
      }
    });

    // Mouse up
    canvas.on('mouse:up', () => {
      if (isDrawingRef.current && (activeTool === 'pen' || activeTool === 'highlighter')) {
        finishDrawing();
      } else if (activeTool === 'rectangle' || activeTool === 'circle' || activeTool === 'line') {
        finishShape();
      }
    });

    // Zoom with mouse wheel
    canvas.on('mouse:wheel', (options) => {
      const delta = options.e.deltaY;
      const zoomFactor = delta > 0 ? 0.9 : 1.1;
      const newZoom = Math.max(0.1, Math.min(5, zoom * zoomFactor));
      setZoom(newZoom);
      canvas.setZoom(newZoom);
      options.e.preventDefault();
      options.e.stopPropagation();
    });
  };

  const handleShapeStart = (x: number, y: number) => {
    // Store start point for shape drawing
    currentPointsRef.current = [{ x, y }];
  };

  const handleShapeUpdate = (x: number, y: number) => {
    if (currentPointsRef.current.length === 0) return;
    
    const start = currentPointsRef.current[0];
    const canvas = fabricCanvasRef.current;
    if (!canvas) return;

    // Remove previous shape if exists
    const objects = canvas.getObjects();
    const lastObj = objects[objects.length - 1];
    if (lastObj && lastObj.name === 'temp-shape') {
      canvas.remove(lastObj);
    }

    let shape: fabric.Object | null = null;

    if (activeTool === 'rectangle') {
      shape = createRectangle(start.x, start.y, x - start.x, y - start.y, color, thickness);
    } else if (activeTool === 'circle') {
      const radius = Math.sqrt(Math.pow(x - start.x, 2) + Math.pow(y - start.y, 2));
      shape = createCircle(start.x, start.y, radius, color, thickness);
    } else if (activeTool === 'line') {
      shape = createLine(start.x, start.y, x, y, color, thickness);
    }

    if (shape) {
      (shape as any).name = 'temp-shape';
      canvas.add(shape);
      canvas.renderAll();
    }
  };

  const finishShape = () => {
    const canvas = fabricCanvasRef.current;
    if (!canvas) return;

    const objects = canvas.getObjects();
    const lastObj = objects[objects.length - 1];
    if (lastObj && (lastObj as any).name === 'temp-shape') {
      (lastObj as any).name = undefined;
      broadcastOperation({
        type: 'add-object',
        object: lastObj.toJSON()
      });
    }
  };

  const updateDrawingPath = () => {
    const canvas = fabricCanvasRef.current;
    if (!canvas || currentPointsRef.current.length < 2) return;

    // Remove previous path
    if (currentPathRef.current) {
      canvas.remove(currentPathRef.current);
    }

    // Create new path
    const path = activeTool === 'pen' 
      ? createPenPath(currentPointsRef.current, color, thickness)
      : createHighlighterPath(currentPointsRef.current, color, thickness);

    currentPathRef.current = path;
    canvas.add(path);
    canvas.renderAll();
  };

  const finishDrawing = () => {
    if (currentPathRef.current) {
      broadcastOperation({
        type: 'add-object',
        object: currentPathRef.current.toJSON()
      });
      currentPathRef.current = null;
    }
    isDrawingRef.current = false;
    currentPointsRef.current = [];
  };

  const handleTextCreation = (x: number, y: number) => {
    const canvas = fabricCanvasRef.current;
    if (!canvas) return;

    const text = createText('Click to edit', x, y, color);
    canvas.add(text);
    canvas.setActiveObject(text);
    text.enterEditing();
    canvas.renderAll();

    broadcastOperation({
      type: 'add-object',
      object: text.toJSON()
    });
  };

  const handleEraser = (options: fabric.IEvent) => {
    const canvas = fabricCanvasRef.current;
    if (!canvas) return;

    const pointer = canvas.getPointer(options.e);
    const objects = canvas.getObjects();

    // Find object under pointer
    for (let i = objects.length - 1; i >= 0; i--) {
      const obj = objects[i];
      if (obj.containsPoint(pointer)) {
        // Check if it's a locked vocabulary card (teacher-only)
        if ((obj as any).vocabularyCard && (obj as any).locked && userRole !== 'teacher') {
          return; // Students can't erase locked vocabulary cards
        }
        
        broadcastOperation({
          type: 'remove-object',
          objectId: (obj as any).id || i
        });
        canvas.remove(obj);
        canvas.renderAll();
        break;
      }
    }
  };

  const handlePointerTool = (x: number, y: number) => {
    const canvas = fabricCanvasRef.current;
    if (!canvas) return;

    // Create temporary highlight circle
    const circle = new fabric.Circle({
      left: x - 20,
      top: y - 20,
      radius: 20,
      fill: 'rgba(11, 108, 245, 0.3)',
      stroke: '#0b6cf5',
      strokeWidth: 2,
      selectable: false,
      evented: false
    });

    canvas.add(circle);
    canvas.renderAll();

    // Remove after 1 second
    setTimeout(() => {
      canvas.remove(circle);
      canvas.renderAll();
    }, 1000);
  };

  const handleImageUpload = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = (e) => {
      const file = (e.target as HTMLInputElement).files?.[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (event) => {
        const imgUrl = event.target?.result as string;
        fabric.Image.fromURL(imgUrl, (img) => {
          const canvas = fabricCanvasRef.current;
          if (!canvas) return;

          // Scale image to fit canvas
          const scale = Math.min(
            (canvas.getWidth() * 0.5) / img.width!,
            (canvas.getHeight() * 0.5) / img.height!
          );
          img.scale(scale);
          img.set({
            left: canvas.getWidth() / 2 - (img.width! * scale) / 2,
            top: canvas.getHeight() / 2 - (img.height! * scale) / 2
          });

          canvas.add(img);
          canvas.setActiveObject(img);
          canvas.renderAll();

          broadcastOperation({
            type: 'add-object',
            object: img.toJSON()
          });
        });
      };
      reader.readAsDataURL(file);
    };
    input.click();
  };

  const handleStickyNoteCreation = (x: number, y: number) => {
    const canvas = fabricCanvasRef.current;
    if (!canvas) return;

    const stickyNote = createStickyNote('New note', x, y);
    canvas.add(stickyNote);
    canvas.setActiveObject(stickyNote);
    canvas.renderAll();

    broadcastOperation({
      type: 'add-object',
      object: stickyNote.toJSON()
    });
  };

  const addVocabularyCardToBoard = useCallback((word: string, definition: string, locked: boolean = false) => {
    const canvas = fabricCanvasRef.current;
    if (!canvas) return;

    const centerX = canvas.getWidth() / 2;
    const centerY = canvas.getHeight() / 2;
    const card = createVocabularyCard(word, definition, centerX - 125, centerY - 90, locked);
    
    (card as any).vocabularyCard = true;
    (card as any).locked = locked;
    (card as any).word = word;
    (card as any).definition = definition;

    canvas.add(card);
    canvas.setActiveObject(card);
    canvas.renderAll();

    broadcastOperation({
      type: 'add-object',
      object: card.toJSON()
    });
  }, []);

  const sendCursorPosition = (x: number, y: number) => {
    pollingRef.current?.send({
      type: 'cursor-move',
      x,
      y
    });
  };

  const handleCursorMove = useCallback((data: any) => {
    if (data.userId === userId) return;

    setCursorPresences(prev => {
      const newPresences = new Map(prev);
      newPresences.set(data.userId, {
        userId: data.userId,
        userName: data.userName,
        x: data.x,
        y: data.y
      });
      return newPresences;
    });
  }, [userId]);

  const handleRemoteOperation = useCallback((data: any) => {
    if (data.userId === userId) return;

    const canvas = fabricCanvasRef.current;
    if (!canvas) return;

    if (data.type === 'add-object') {
      fabric.util.enlivenObjects([data.object], (objects) => {
        objects.forEach(obj => canvas.add(obj));
        canvas.renderAll();
      });
    } else if (data.type === 'remove-object') {
      const objects = canvas.getObjects();
      if (objects[data.objectId]) {
        canvas.remove(objects[data.objectId]);
        canvas.renderAll();
      }
    } else if (data.type === 'update-object') {
      const objects = canvas.getObjects();
      const obj = objects.find((o: any) => o.id === data.objectId);
      if (obj) {
        obj.set(data.properties);
        canvas.renderAll();
      }
    }
  }, [userId]);

  const handleUserJoined = useCallback((data: any) => {
    // Send current canvas state to new user
    const canvas = fabricCanvasRef.current;
    if (canvas && data.userId !== userId) {
      const objects = canvas.getObjects();
      objects.forEach(obj => {
        pollingRef.current?.send({
          type: 'whiteboard-operation',
          operation: {
            type: 'add-object',
            object: obj.toJSON()
          },
          targetUserId: data.userId
        });
      });
    }
  }, [userId]);

  const handleUserLeft = useCallback((data: any) => {
    setCursorPresences(prev => {
      const newPresences = new Map(prev);
      newPresences.delete(data.userId);
      return newPresences;
    });
  }, []);

  const broadcastOperation = (operation: any) => {
    pollingRef.current?.send({
      type: 'whiteboard-operation',
      operation
    });
  };

  const loadWhiteboardState = async () => {
    try {
      const response = await fetch(`/api/sessions.php?action=get-state&sessionId=${sessionId}`);
      if (response.ok) {
        const data = await response.json();
        if (data.state) {
          const canvas = fabricCanvasRef.current;
          if (canvas) {
            canvas.loadFromJSON(data.state, () => {
              canvas.renderAll();
            });
          }
        }
      }
    } catch (error) {
      console.error('Error loading whiteboard state:', error);
    }
  };

  const saveWhiteboardState = async () => {
    const canvas = fabricCanvasRef.current;
    if (!canvas) return;

    try {
      const state = JSON.stringify(canvas.toJSON());
      await fetch('/api/sessions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save-state',
          sessionId,
          state
        })
      });
    } catch (error) {
      console.error('Error saving whiteboard state:', error);
    }
  };

  return (
    <div className="whiteboard-container">
      <div className="whiteboard-canvas-wrapper">
        <canvas ref={canvasRef} />
        {/* Cursor presences */}
        {Array.from(cursorPresences.values()).map(presence => (
          <div
            key={presence.userId}
            className="cursor-presence"
            style={{
              left: presence.x,
              top: presence.y,
              transform: 'translate(-50%, -50%)'
            }}
          >
            <div className="cursor-presence-cursor"></div>
            <div className="cursor-presence-label">{presence.userName}</div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default Whiteboard;

