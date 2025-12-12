# React Classroom Bundle - Build Instructions

## ⚠️ CRITICAL: This Must Be Done Before Classroom Works

The classroom requires a built React bundle. Without this, the classroom page will be blank.

## Prerequisites

1. **Install Node.js** (v16 or higher)
   - Download from: https://nodejs.org/
   - Verify installation: `node --version` and `npm --version`

## Build Steps

### 1. Navigate to Project Root
```bash
cd "C:\xampp\htdocs\Web page\Staten-Academy"
```

### 2. Install Dependencies
```bash
npm install
```

This will install:
- React 18.2.0
- React DOM 18.2.0
- Vite 5.0.8
- TypeScript 5.3.3
- Fabric.js 5.3.0 (for whiteboard)
- Socket.io-client 4.6.1
- date-fns 2.30.0
- And all dev dependencies

### 3. Build the Bundle
```bash
npm run build
```

This will:
1. Run TypeScript compiler (`tsc`)
2. Build with Vite (`vite build`)
3. Output to: `public/assets/js/classroom.bundle.js`

### 4. Verify Build Success

Check that the file exists:
```bash
dir "public\assets\js\classroom.bundle.js"
```

The file should be:
- **Size**: > 100KB (typically 200-500KB)
- **Location**: `public/assets/js/classroom.bundle.js`
- **Type**: JavaScript bundle file

## Build Configuration

The build is configured in:
- **`vite.config.js`**: Vite build configuration
- **`tsconfig.json`**: TypeScript configuration
- **`package.json`**: Build scripts and dependencies

## Expected Output

After successful build:
```
✓ built in XXXms
```

The bundle will be created at:
```
public/assets/js/classroom.bundle.js
```

## Troubleshooting

### Error: "npm is not recognized"
- **Solution**: Install Node.js from nodejs.org
- Add Node.js to system PATH

### Error: "Cannot find module"
- **Solution**: Run `npm install` first
- Check `node_modules` folder exists

### Error: TypeScript errors
- **Solution**: Check `tsconfig.json` is correct
- Verify all source files exist in `src/` directory

### Build succeeds but classroom still blank
- **Solution**: 
  1. Check browser console for errors
  2. Verify file path: `public/assets/js/classroom.bundle.js`
  3. Check file permissions (must be readable)
  4. Clear browser cache

## Development Mode (Optional)

For development with hot reload:
```bash
npm run dev
```

This starts Vite dev server (usually on port 5173).

## Production Build

For production:
```bash
npm run build
```

This creates an optimized, minified bundle.

## Verification Checklist

After building, verify:
- [ ] `public/assets/js/classroom.bundle.js` exists
- [ ] File size > 100KB
- [ ] Classroom page loads (not blank)
- [ ] Browser console shows no bundle loading errors
- [ ] React app initializes (check browser console)

## Next Steps

After successful build:
1. Test classroom page loads
2. Test teacher "Test Classroom" button
3. Test student joining scheduled lesson
4. Verify video/audio works
5. Verify whiteboard works

---

**Status**: ⏳ **AWAITING BUILD**
**Action Required**: Run `npm install && npm run build`

