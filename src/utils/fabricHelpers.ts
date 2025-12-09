import { fabric } from 'fabric';

export const createPenPath = (points: { x: number; y: number }[], color: string, thickness: number): fabric.Path => {
  const pathData = points.map((p, i) => 
    i === 0 ? `M ${p.x} ${p.y}` : `L ${p.x} ${p.y}`
  ).join(' ');

  return new fabric.Path(pathData, {
    stroke: color,
    strokeWidth: thickness,
    fill: '',
    strokeLineCap: 'round',
    strokeLineJoin: 'round'
  });
};

export const createHighlighterPath = (points: { x: number; y: number }[], color: string, thickness: number): fabric.Path => {
  const path = createPenPath(points, color, thickness);
  path.set({
    opacity: 0.4,
    strokeLineCap: 'round',
    strokeLineJoin: 'round'
  });
  return path;
};

export const createRectangle = (x: number, y: number, width: number, height: number, color: string, thickness: number): fabric.Rect => {
  return new fabric.Rect({
    left: x,
    top: y,
    width: Math.abs(width),
    height: Math.abs(height),
    stroke: color,
    strokeWidth: thickness,
    fill: 'transparent'
  });
};

export const createCircle = (x: number, y: number, radius: number, color: string, thickness: number): fabric.Circle => {
  return new fabric.Circle({
    left: x - radius,
    top: y - radius,
    radius: radius,
    stroke: color,
    strokeWidth: thickness,
    fill: 'transparent'
  });
};

export const createLine = (x1: number, y1: number, x2: number, y2: number, color: string, thickness: number): fabric.Line => {
  return new fabric.Line([x1, y1, x2, y2], {
    stroke: color,
    strokeWidth: thickness
  });
};

export const createText = (text: string, x: number, y: number, color: string, fontSize: number = 24): fabric.Text => {
  return new fabric.Text(text, {
    left: x,
    top: y,
    fontSize: fontSize,
    fill: color,
    editable: true
  });
};

export const createStickyNote = (text: string, x: number, y: number, color: string = '#ffeb3b'): fabric.Group => {
  const rect = new fabric.Rect({
    width: 200,
    height: 150,
    fill: color,
    stroke: '#000',
    strokeWidth: 1
  });

  const textObj = new fabric.Text(text, {
    fontSize: 16,
    fill: '#000',
    left: 10,
    top: 10,
    width: 180,
    editable: true
  });

  return new fabric.Group([rect, textObj], {
    left: x,
    top: y
  });
};

export const createVocabularyCard = (
  word: string,
  definition: string,
  x: number,
  y: number,
  locked: boolean = false
): fabric.Group => {
  const cardWidth = 250;
  const cardHeight = 180;
  const padding = 15;

  // Card background
  const bg = new fabric.Rect({
    width: cardWidth,
    height: cardHeight,
    fill: '#ffffff',
    stroke: '#0b6cf5',
    strokeWidth: 2,
    rx: 8,
    ry: 8
  });

  // Word text
  const wordText = new fabric.Text(word, {
    fontSize: 20,
    fontWeight: 'bold',
    fill: '#004080',
    left: padding,
    top: padding,
    width: cardWidth - padding * 2
  });

  // Definition text
  const defText = new fabric.Text(definition, {
    fontSize: 14,
    fill: '#333',
    left: padding,
    top: padding + 35,
    width: cardWidth - padding * 2,
    splitByGrapheme: true
  });

  // Lock icon if locked
  const elements: fabric.Object[] = [bg, wordText, defText];
  if (locked) {
    const lockIcon = new fabric.Text('🔒', {
      fontSize: 16,
      left: cardWidth - 30,
      top: padding
    });
    elements.push(lockIcon);
  }

  const group = new fabric.Group(elements, {
    left: x,
    top: y,
    lockMovementX: locked,
    lockMovementY: locked,
    lockRotation: locked,
    lockScalingX: locked,
    lockScalingY: locked
  });

  return group;
};



