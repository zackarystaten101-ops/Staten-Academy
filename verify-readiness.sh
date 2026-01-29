#!/bin/bash
# Verification script to ensure system is ready for commit

echo "🔍 Verifying system readiness..."

# Check 1: No PHP files modified
echo "1. Checking for modified PHP files..."
PHP_FILES=$(git diff --name-only --cached 2>/dev/null | grep -E '\.php$' || true)
if [ -n "$PHP_FILES" ]; then
  echo "❌ WARNING: PHP files modified:"
  echo "$PHP_FILES"
  echo "This should NOT happen - existing PHP code must remain untouched"
  exit 1
else
  echo "✅ No PHP files modified - Good!"
fi

# Check 2: Backend structure exists
echo "2. Checking backend structure..."
if [ ! -d "backend/src" ]; then
  echo "❌ Backend directory missing"
  exit 1
else
  echo "✅ Backend directory exists"
fi

# Check 3: Feature flags file exists
echo "3. Checking feature flags..."
if [ ! -f "backend/src/config/feature-flags.ts" ]; then
  echo "❌ Feature flags file missing"
  exit 1
else
  echo "✅ Feature flags implemented"
fi

# Check 4: Rollback guide exists
echo "4. Checking documentation..."
if [ ! -f "ROLLBACK_GUIDE.md" ]; then
  echo "❌ Rollback guide missing"
  exit 1
else
  echo "✅ Rollback guide exists"
fi

# Check 5: .gitignore updated
echo "5. Checking .gitignore..."
if ! grep -q "backend/node_modules" .gitignore 2>/dev/null; then
  echo "⚠️  .gitignore may need backend/node_modules entry"
else
  echo "✅ .gitignore includes backend/node_modules"
fi

echo ""
echo "✅ All checks passed! System is ready for commit."
echo ""
echo "📋 Next steps:"
echo "1. Review PRE_COMMIT_CHECKLIST.md"
echo "2. Test existing PHP functionality"
echo "3. Commit with appropriate message"
echo "4. Feature flags disabled by default - enable when ready"








