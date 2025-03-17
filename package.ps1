# Package Alt1 app for distribution
$version = "1.0.0"
$zipName = "soul-obelisk-tracker-v$version.zip"

# Create a temporary directory for packaging
New-Item -ItemType Directory -Force -Path "dist" | Out-Null

# Copy required files
Copy-Item "index.html" -Destination "dist/"
Copy-Item "appconfig.json" -Destination "dist/"
Copy-Item "icon.png" -Destination "dist/"
Copy-Item "README.md" -Destination "dist/"

# Create the zip file
Compress-Archive -Path "dist/*" -DestinationPath $zipName -Force

# Clean up
Remove-Item -Recurse -Force "dist"

Write-Host "Package created: $zipName" 