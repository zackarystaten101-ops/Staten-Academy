import React, { useState } from 'react';
import { useWhiteboardStore, ToolType } from '../stores/whiteboardStore';

const WhiteboardToolbar: React.FC = () => {
  const { activeTool, color, thickness, setActiveTool, setColor, setThickness } = useWhiteboardStore();
  const [showColorPicker, setShowColorPicker] = useState(false);
  const [showThicknessSlider, setShowThicknessSlider] = useState(false);

  const colors = [
    '#000000', '#ffffff', '#ff0000', '#00ff00', '#0000ff',
    '#ffff00', '#ff00ff', '#00ffff', '#ffa500', '#800080',
    '#0b6cf5', '#004080', '#28a745', '#dc3545', '#ffc107'
  ];

  const tools: { type: ToolType; icon: string; label: string }[] = [
    { type: 'select', icon: 'fa-mouse-pointer', label: 'Select' },
    { type: 'pen', icon: 'fa-pen', label: 'Pen' },
    { type: 'highlighter', icon: 'fa-highlighter', label: 'Highlighter' },
    { type: 'eraser', icon: 'fa-eraser', label: 'Eraser' },
    { type: 'rectangle', icon: 'fa-square', label: 'Rectangle' },
    { type: 'circle', icon: 'fa-circle', label: 'Circle' },
    { type: 'line', icon: 'fa-minus', label: 'Line' },
    { type: 'text', icon: 'fa-font', label: 'Text' },
    { type: 'image', icon: 'fa-image', label: 'Image' },
    { type: 'sticky-note', icon: 'fa-sticky-note', label: 'Sticky Note' },
    { type: 'pointer', icon: 'fa-hand-pointer', label: 'Pointer' }
  ];

  const handleToolSelect = (tool: ToolType) => {
    setActiveTool(tool);
    setShowColorPicker(tool === 'pen' || tool === 'highlighter' || tool === 'text' || tool === 'rectangle' || tool === 'circle' || tool === 'line');
    setShowThicknessSlider(tool === 'pen' || tool === 'highlighter' || tool === 'rectangle' || tool === 'circle' || tool === 'line');
  };

  return (
    <div className="whiteboard-toolbar">
      {tools.map(tool => (
        <button
          key={tool.type}
          className={`toolbar-button ${activeTool === tool.type ? 'active' : ''}`}
          onClick={() => handleToolSelect(tool.type)}
          title={tool.label}
        >
          <i className={`fas ${tool.icon}`}></i>
        </button>
      ))}

      {(showColorPicker || showThicknessSlider) && (
        <div className="tool-options">
          {showColorPicker && (
            <div>
              <label style={{ display: 'block', marginBottom: '10px', fontWeight: 600, fontSize: '0.9rem' }}>
                Color
              </label>
              <div className="color-picker">
                {colors.map(c => (
                  <div
                    key={c}
                    className={`color-option ${color === c ? 'selected' : ''}`}
                    style={{ backgroundColor: c, borderColor: c === '#ffffff' ? '#ddd' : c }}
                    onClick={() => setColor(c)}
                    title={c}
                  />
                ))}
              </div>
            </div>
          )}

          {showThicknessSlider && (
            <div>
              <label style={{ display: 'block', marginBottom: '10px', fontWeight: 600, fontSize: '0.9rem' }}>
                Thickness: {thickness}px
              </label>
              <input
                type="range"
                min="1"
                max="20"
                value={thickness}
                onChange={(e) => setThickness(parseInt(e.target.value))}
                className="thickness-slider"
              />
            </div>
          )}

          <div style={{ marginTop: '15px', display: 'flex', gap: '5px' }}>
            <button
              className="toolbar-button"
              onClick={() => {
                // Undo functionality would be handled by whiteboard store
                const { undo } = useWhiteboardStore.getState();
                undo();
              }}
              title="Undo"
              style={{ width: '100%', margin: 0 }}
            >
              <i className="fas fa-undo"></i>
            </button>
            <button
              className="toolbar-button"
              onClick={() => {
                // Redo functionality would be handled by whiteboard store
                const { redo } = useWhiteboardStore.getState();
                redo();
              }}
              title="Redo"
              style={{ width: '100%', margin: 0 }}
            >
              <i className="fas fa-redo"></i>
            </button>
          </div>

          <button
            className="toolbar-button danger"
            onClick={() => {
              if (confirm('Clear the entire whiteboard? This cannot be undone.')) {
                const canvas = (window as any).fabricCanvas;
                if (canvas) {
                  canvas.clear();
                  canvas.backgroundColor = '#f5f5f5';
                  canvas.renderAll();
                }
              }
            }}
            title="Clear Board"
            style={{ width: '100%', margin: '10px 0 0 0', background: '#dc3545', color: 'white' }}
          >
            <i className="fas fa-trash"></i> Clear Board
          </button>
        </div>
      )}
    </div>
  );
};

export default WhiteboardToolbar;


