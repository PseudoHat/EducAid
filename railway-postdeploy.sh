#!/usr/bin/env bash
# Post-deploy hook for Railway - ensures symlink is created
# This runs AFTER the build completes

echo "========================================="
echo "Post-Deploy: Railway Volume Symlink Setup"
echo "========================================="

# Wait a moment for filesystem to settle
sleep 2

if [ -d "/mnt/assets/uploads" ]; then
    echo "✓ Railway volume detected"
    
    # Forcefully remove any existing directory
    if [ -e /app/assets/uploads ] || [ -L /app/assets/uploads ]; then
        echo "Removing existing /app/assets/uploads..."
        chmod -R 777 /app/assets/uploads 2>/dev/null || true
        rm -rf /app/assets/uploads 2>/dev/null || true
    fi
    
    # Ensure parent directory exists
    mkdir -p /app/assets 2>/dev/null || true
    
    # Create symlink
    ln -sf /mnt/assets/uploads /app/assets/uploads
    
    # Verify
    if [ -L /app/assets/uploads ]; then
        TARGET=$(readlink /app/assets/uploads)
        echo "✓ Symlink created: /app/assets/uploads -> $TARGET"
        
        # Test if it works
        if [ -d /app/assets/uploads/temp ]; then
            echo "✓ Symlink is functional - can access volume"
        else
            echo "⚠ Symlink created but cannot access volume contents"
        fi
    else
        echo "✗ Failed to create symlink"
        exit 1
    fi
else
    echo "✗ No Railway volume found at /mnt/assets/uploads"
    exit 1
fi

echo "✓ Post-deploy setup complete"
echo "========================================="
