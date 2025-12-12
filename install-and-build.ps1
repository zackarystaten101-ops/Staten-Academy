# Staten Academy - Node.js Installation and Build Script
# This script will help install Node.js and build the React classroom bundle

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Staten Academy - Build Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if Node.js is already installed
$nodeInstalled = $false
try {
    $nodeVersion = node --version 2>$null
    if ($nodeVersion) {
        Write-Host "✓ Node.js is already installed: $nodeVersion" -ForegroundColor Green
        $nodeInstalled = $true
    }
} catch {
    $nodeInstalled = $false
}

# Check if npm is available
$npmInstalled = $false
if ($nodeInstalled) {
    try {
        $npmVersion = npm --version 2>$null
        if ($npmVersion) {
            Write-Host "✓ npm is already installed: $npmVersion" -ForegroundColor Green
            $npmInstalled = $true
        }
    } catch {
        $npmInstalled = $false
    }
}

# If Node.js is not installed, provide installation instructions
if (-not $nodeInstalled) {
    Write-Host "⚠ Node.js is not installed." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "To install Node.js:" -ForegroundColor Yellow
    Write-Host "1. Download Node.js from: https://nodejs.org/" -ForegroundColor White
    Write-Host "2. Choose the LTS version (recommended)" -ForegroundColor White
    Write-Host "3. Run the installer and follow the prompts" -ForegroundColor White
    Write-Host "4. Make sure to check 'Add to PATH' during installation" -ForegroundColor White
    Write-Host ""
    Write-Host "After installation, close and reopen this terminal, then run this script again." -ForegroundColor Yellow
    Write-Host ""
    
    # Offer to open the download page
    $response = Read-Host "Would you like to open the Node.js download page now? (Y/N)"
    if ($response -eq 'Y' -or $response -eq 'y') {
        Start-Process "https://nodejs.org/"
    }
    
    exit
}

# If we get here, Node.js is installed
Write-Host ""
Write-Host "Building React Classroom Bundle..." -ForegroundColor Cyan
Write-Host ""

# Navigate to project directory
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $projectRoot

# Check if node_modules exists
if (-not (Test-Path "node_modules")) {
    Write-Host "Installing dependencies..." -ForegroundColor Yellow
    Write-Host "This may take a few minutes..." -ForegroundColor Yellow
    Write-Host ""
    
    npm install
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "✗ Failed to install dependencies" -ForegroundColor Red
        Write-Host "Please check the error messages above." -ForegroundColor Red
        exit 1
    }
    
    Write-Host ""
    Write-Host "✓ Dependencies installed successfully" -ForegroundColor Green
    Write-Host ""
}

# Build the bundle
Write-Host "Building React bundle..." -ForegroundColor Yellow
Write-Host ""

npm run build

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "✗ Build failed" -ForegroundColor Red
    Write-Host "Please check the error messages above." -ForegroundColor Red
    exit 1
}

# Verify the bundle was created
$bundlePath = "public\assets\js\classroom.bundle.js"
if (Test-Path $bundlePath) {
    $fileSize = (Get-Item $bundlePath).Length / 1KB
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "✓ Build Successful!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Bundle created: $bundlePath" -ForegroundColor Green
    Write-Host "File size: $([math]::Round($fileSize, 2)) KB" -ForegroundColor Green
    Write-Host ""
    Write-Host "The classroom should now work!" -ForegroundColor Green
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "✗ Build completed but bundle file not found" -ForegroundColor Red
    Write-Host "Expected location: $bundlePath" -ForegroundColor Red
    Write-Host "Please check the build output above for errors." -ForegroundColor Red
    exit 1
}

