$searchDirs = @("C:\Users\Admin\Downloads", "C:\Users\Admin\Documents", "C:\Users\Admin\Desktop", "C:\Users\Admin\AppData\Local\Temp")
$targetNames = @(
    'popular_dog_breeds.png',
    'silent_evidence.png',
    'the_silent_conversation.pcap',
    'memory_dump.bin',
    'the_unwritten_algorithm.zip',
    'incident_report.log',
    'memory_capture.bin',
    'evidence_notes.txt',
    'password.enc',
    'secret.enc'
)

foreach ($dir in $searchDirs) {
    if (Test-Path $dir) {
        Get-ChildItem -Path $dir -Recurse -File -ErrorAction SilentlyContinue | Where-Object { $targetNames -contains $_.Name } | ForEach-Object {
            Write-Host "$($_.Length) bytes: $($_.FullName)"
        }
    }
}

