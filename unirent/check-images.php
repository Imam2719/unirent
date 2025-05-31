<?php
// This script helps verify image paths and accessibility

// Define the image directories to check
$directories = [
    'assets/images/hero',
    'assets/images/categories',
    'assets/images/auth',
    'assets/images/icons',
    'assets/images'
];

echo "<h1>Image Path Checker</h1>";

// Check if directories exist
foreach ($directories as $dir) {
    echo "<h2>Checking directory: $dir</h2>";
    
    if (file_exists($dir) && is_dir($dir)) {
        echo "<p style='color: green;'>✓ Directory exists</p>";
        
        // List all files in the directory
        $files = scandir($dir);
        echo "<h3>Files in directory:</h3>";
        echo "<ul>";
        
        $imageCount = 0;
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $filePath = "$dir/$file";
                $fileUrl = str_replace(' ', '%20', $filePath);
                
                if (is_file($filePath)) {
                    $imageCount++;
                    $fileSize = filesize($filePath);
                    $fileSizeFormatted = $fileSize < 1024 ? "$fileSize bytes" : 
                                        ($fileSize < 1048576 ? round($fileSize/1024, 2)." KB" : 
                                        round($fileSize/1048576, 2)." MB");
                    
                    echo "<li>";
                    echo "<strong>$file</strong> ($fileSizeFormatted)<br>";
                    echo "<img src='$fileUrl' style='max-width: 200px; max-height: 200px; border: 1px solid #ccc;'><br>";
                    echo "Full path: $filePath<br>";
                    echo "URL: $fileUrl";
                    echo "</li>";
                }
            }
        }
        
        if ($imageCount == 0) {
            echo "<li style='color: red;'>No image files found in this directory!</li>";
        }
        
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ Directory does not exist!</p>";
        echo "<p>Creating directory...</p>";
        
        if (mkdir($dir, 0755, true)) {
            echo "<p style='color: green;'>Directory created successfully!</p>";
        } else {
            echo "<p style='color: red;'>Failed to create directory. Check permissions.</p>";
        }
    }
    
    echo "<hr>";
}
?>