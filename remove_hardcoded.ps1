$content = Get-Content mainDashboard.php
$filteredContent = @()
$skipUntilClosingDiv = $false

foreach ($line in $content) {
    if ($line -match 'review-student-item.*data-studentid="(12345|59828881|59829532|59829536|59829663|67890)"') {
        $skipUntilClosingDiv = $true
        continue
    }
    
    if ($skipUntilClosingDiv -and $line -match '^\s*</div>\s*$') {
        $skipUntilClosingDiv = $false
        continue
    }
    
    if (-not $skipUntilClosingDiv) {
        $filteredContent += $line
    }
}

$filteredContent | Set-Content mainDashboard.php