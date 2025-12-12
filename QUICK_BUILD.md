# Quick Build Guide

## Option 1: Use the PowerShell Script (Easiest)

1. **Right-click** on `install-and-build.ps1`
2. Select **"Run with PowerShell"**
3. Follow the prompts

The script will:
- Check if Node.js is installed
- Guide you to install it if needed
- Install npm dependencies
- Build the React bundle

## Option 2: Manual Installation

### Step 1: Install Node.js

1. Download from: **https://nodejs.org/**
2. Choose **LTS version** (recommended)
3. Run the installer
4. **Important**: Check "Add to PATH" during installation
5. Restart your terminal/PowerShell

### Step 2: Verify Installation

Open PowerShell and run:
```powershell
node --version
npm --version
```

You should see version numbers.

### Step 3: Build the Bundle

Navigate to your project directory:
```powershell
cd "C:\xampp\htdocs\Web page\Staten-Academy"
```

Install dependencies:
```powershell
npm install
```

Build the bundle:
```powershell
npm run build
```

### Step 4: Verify Build

Check that the file exists:
```powershell
dir "public\assets\js\classroom.bundle.js"
```

The file should be > 100KB.

## Troubleshooting

### "node is not recognized"
- Node.js is not installed or not in PATH
- Reinstall Node.js and make sure to check "Add to PATH"
- Restart your terminal after installation

### "npm install" fails
- Check your internet connection
- Try: `npm install --verbose` to see detailed errors
- Check if antivirus is blocking npm

### Build succeeds but file not found
- Check `vite.config.js` output path
- Look in `public/assets/js/` directory
- Check for build errors in console output

### TypeScript errors during build
- All source files should be in `src/` directory
- Check `tsconfig.json` is correct
- Verify all imports are correct

## Success Indicators

After successful build:
- ✅ `public/assets/js/classroom.bundle.js` exists
- ✅ File size > 100KB
- ✅ Classroom page loads (not blank)
- ✅ No errors in browser console

---

**Need Help?** Check `BUILD_INSTRUCTIONS.md` for detailed instructions.

