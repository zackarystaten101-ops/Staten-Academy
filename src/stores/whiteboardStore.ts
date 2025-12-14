import { create } from 'zustand';

export type ToolType = 
  | 'pen'
  | 'highlighter'
  | 'eraser'
  | 'rectangle'
  | 'circle'
  | 'line'
  | 'text'
  | 'image'
  | 'sticky-note'
  | 'pointer'
  | 'select';

export interface WhiteboardState {
  activeTool: ToolType;
  color: string;
  thickness: number;
  zoom: number;
  panX: number;
  panY: number;
  history: any[];
  historyIndex: number;
}

interface WhiteboardStore extends WhiteboardState {
  setActiveTool: (tool: ToolType) => void;
  setColor: (color: string) => void;
  setThickness: (thickness: number) => void;
  setZoom: (zoom: number) => void;
  setPan: (x: number, y: number) => void;
  addToHistory: (state: any) => void;
  undo: () => void;
  redo: () => void;
  reset: () => void;
}

const defaultState: WhiteboardState = {
  activeTool: 'pen',
  color: '#000000',
  thickness: 3,
  zoom: 1,
  panX: 0,
  panY: 0,
  history: [],
  historyIndex: -1
};

export const useWhiteboardStore = create<WhiteboardStore>((set, get) => ({
  ...defaultState,

  setActiveTool: (tool) => set({ activeTool: tool }),

  setColor: (color) => set({ color }),

  setThickness: (thickness) => set({ thickness }),

  setZoom: (zoom) => set({ zoom }),

  setPan: (x, y) => set({ panX: x, panY: y }),

  addToHistory: (state) => {
    const { history, historyIndex } = get();
    const newHistory = history.slice(0, historyIndex + 1);
    newHistory.push(state);
    set({
      history: newHistory,
      historyIndex: newHistory.length - 1
    });
  },

  undo: () => {
    const { historyIndex } = get();
    if (historyIndex > 0) {
      set({ historyIndex: historyIndex - 1 });
      return get().history[historyIndex - 1];
    }
    return null;
  },

  redo: () => {
    const { history, historyIndex } = get();
    if (historyIndex < history.length - 1) {
      set({ historyIndex: historyIndex + 1 });
      return history[historyIndex + 1];
    }
    return null;
  },

  reset: () => set(defaultState)
}));







