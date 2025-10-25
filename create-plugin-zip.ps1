# WordPress Plugin ZIP Creator
# Creates a ZIP file with proper UNIX/Linux-compatible directory structure

# Get the plugin directory name (current directory)
$pluginDir = Split-Path -Leaf (Get-Location)

# Create plugin subfolder if it doesn't exist
if (-not (Test-Path "plugin")) {
    New-Item -ItemType Directory -Path "plugin" | Out-Null
}

# Remove old ZIP file if it exists
$zipPath = "plugin\$pluginDir.zip"
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Write-Host "Creating ZIP: $zipPath" -ForegroundColor Green

# Get all files except excluded patterns
$excludePatterns = @(
    'node_modules',
    'src-svelte',
    '\.git',
    '\.vscode',
    '\.idea',
    '\\plugin\\',
    'create-plugin-zip.ps1',
    '\.DS_Store',
    'Thumbs\.db',
    '\.claude'
)

$excludeExtensions = @('.md', '.log')

# Get all files recursively
$files = Get-ChildItem -Recurse -File | Where-Object {
    $file = $_
    $shouldExclude = $false

    # Check file extension
    if ($excludeExtensions -contains $file.Extension) {
        return $false
    }

    # Check if file path contains excluded pattern
    foreach ($pattern in $excludePatterns) {
        if ($file.FullName -match $pattern) {
            $shouldExclude = $true
            break
        }
    }

    -not $shouldExclude
}

# Create ZIP with proper directory structure
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

try {
    foreach ($file in $files) {
        # Get relative path from current directory
        $relativePath = $file.FullName.Substring((Get-Location).Path.Length + 1)

        # Convert backslashes to forward slashes for UNIX/Linux compatibility
        $zipEntryName = "$pluginDir/" + $relativePath.Replace('\', '/')

        # Add file to ZIP
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zip,
            $file.FullName,
            $zipEntryName,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }

    Write-Host "Successfully created: $zipPath" -ForegroundColor Green
    Write-Host "Files included: $($files.Count)" -ForegroundColor Cyan
}
finally {
    $zip.Dispose()
}
