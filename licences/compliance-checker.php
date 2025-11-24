<?php
declare(strict_types=1);

/**
 * Teampass License Compliance Checker (Memory Optimized)
 * Generates a comprehensive license compliance report for all dependencies
 * 
 * @author Nils Laumaillé
 * @license GPL-3.0
 */

class LicenseComplianceChecker
{
    private const GPL_COMPATIBLE_LICENSES = [
        'MIT', 'BSD-2-Clause', 'BSD-3-Clause', 'Apache-2.0', 'LGPL-2.1',
        'LGPL-3.0', 'GPL-2.0', 'GPL-3.0', 'ISC', 'Unlicense', 'CC0-1.0'
    ];
    
    private int $phpCount = 0;
    private int $jsCount = 0;
    private int $errorCount = 0;
    private int $warningCount = 0;
    private $reportFile;
    
    /**
     * Main execution method
     * 
     * @return void
     */
    public function run(): void
    {
        echo "=== Teampass License Compliance Checker ===\n\n";
        
        // Open report file for streaming write
        $this->reportFile = fopen(__DIR__ . '/LICENSE_COMPLIANCE_REPORT.md', 'w');
        
        $this->writeHeader();
        $this->processPhpDependencies();
        $this->processJsDependencies();
        $this->writeFooter();
        
        fclose($this->reportFile);
        
        echo "\n✓ Compliance report generated: licences/LICENSE_COMPLIANCE_REPORT.md\n";
        echo "  PHP dependencies: {$this->phpCount}\n";
        echo "  JS dependencies: {$this->jsCount}\n";
        echo "  Errors: {$this->errorCount}\n";
        echo "  Warnings: {$this->warningCount}\n";
        
        if ($this->errorCount > 0) {
            echo "\n⚠️  CRITICAL: Incompatible licenses detected!\n";
            exit(1);
        }
    }
    
    /**
     * Write report header
     * 
     * @return void
     */
    private function writeHeader(): void
    {
        $header = "# Teampass License Compliance Report\n\n";
        $header .= "**Generated:** " . date('Y-m-d H:i:s') . "\n";
        $header .= "**Project License:** GNU General Public License v3.0\n\n";
        
        fwrite($this->reportFile, $header);
    }
    
    /**
     * Process PHP dependencies from composer.lock
     * 
     * @return void
     */
    private function processPhpDependencies(): void
    {
        $lockFile = __DIR__ . '/../composer.lock';
        
        if (!file_exists($lockFile)) {
            $this->errorCount++;
            fwrite($this->reportFile, "❌ **ERROR:** composer.lock not found\n\n");
            return;
        }
        
        echo "Processing PHP dependencies...\n";
        
        // Stream parse JSON to avoid loading everything in memory
        $content = file_get_contents($lockFile);
        $data = json_decode($content, true);
        unset($content); // Free memory
        
        if (!isset($data['packages'])) {
            $this->errorCount++;
            fwrite($this->reportFile, "❌ **ERROR:** Invalid composer.lock format\n\n");
            return;
        }
        
        fwrite($this->reportFile, "## PHP Dependencies (Composer)\n\n");
        fwrite($this->reportFile, "| Package | Version | License | Status |\n");
        fwrite($this->reportFile, "|---------|---------|---------|--------|\n");
        
        foreach ($data['packages'] as $package) {
            $this->phpCount++;
            $this->writePhpDependency($package);
            
            // Progress indicator
            if ($this->phpCount % 20 === 0) {
                echo "  Processed {$this->phpCount} packages...\n";
            }
        }
        
        fwrite($this->reportFile, "\n");
        echo "✓ Processed {$this->phpCount} PHP dependencies\n";
    }
    
    /**
     * Write single PHP dependency to report
     * 
     * @param array $package Package data from composer.lock
     * @return void
     */
    private function writePhpDependency(array $package): void
    {
        $name = $package['name'];
        $version = $package['version'];
        $licenses = $package['license'] ?? ['Unknown'];
        $licenseStr = implode(', ', $licenses);
        
        $status = $this->getComplianceStatus($licenses);
        
        $line = "| {$name} | {$version} | {$licenseStr} | {$status} |\n";
        fwrite($this->reportFile, $line);
    }
    
    /**
     * Process JavaScript dependencies
     * 
     * @return void
     */
    private function processJsDependencies(): void
    {
        $jsFile = __DIR__ . '/javascript-dependencies.json';
        
        fwrite($this->reportFile, "## JavaScript/CSS Dependencies\n\n");
        
        if (!file_exists($jsFile)) {
            $this->warningCount++;
            fwrite($this->reportFile, "⚠️ **WARNING:** javascript-dependencies.json not found\n\n");
            $this->createJsTemplate();
            fwrite($this->reportFile, "Template created at `licences/javascript-dependencies.json`\n\n");
            return;
        }
        
        echo "Processing JavaScript dependencies...\n";
        
        $jsData = json_decode(file_get_contents($jsFile), true);
        
        if (!isset($jsData['dependencies']) || empty($jsData['dependencies'])) {
            fwrite($this->reportFile, "_No JavaScript dependencies registered._\n\n");
            return;
        }
        
        fwrite($this->reportFile, "| Package | Version | License | Status |\n");
        fwrite($this->reportFile, "|---------|---------|---------|--------|\n");
        
        foreach ($jsData['dependencies'] as $dep) {
            $this->jsCount++;
            $this->writeJsDependency($dep);
        }
        
        fwrite($this->reportFile, "\n");
        echo "✓ Processed {$this->jsCount} JavaScript dependencies\n";
    }
    
    /**
     * Write single JavaScript dependency to report
     * 
     * @param array $dep Dependency data
     * @return void
     */
    private function writeJsDependency(array $dep): void
    {
        $name = $dep['name'] ?? 'Unknown';
        $version = $dep['version'] ?? 'Unknown';
        $licenses = $dep['licenses'] ?? ['Unknown'];
        $licenseStr = implode(', ', $licenses);
        
        $status = $this->getComplianceStatus($licenses);
        
        $line = "| {$name} | {$version} | {$licenseStr} | {$status} |\n";
        fwrite($this->reportFile, $line);
    }
    
    /**
     * Get compliance status for licenses
     * 
     * @param array $licenses Array of license identifiers
     * @return string Status string with emoji
     */
    private function getComplianceStatus(array $licenses): string
    {
        if (empty($licenses) || in_array('Unknown', $licenses)) {
            $this->warningCount++;
            return '⚠️ Unknown';
        }
        
        $compatible = false;
        foreach ($licenses as $license) {
            $normalized = trim($license);
            
            // Check if compatible
            foreach (self::GPL_COMPATIBLE_LICENSES as $compat) {
                if (stripos($normalized, $compat) !== false) {
                    $compatible = true;
                    break 2;
                }
            }
        }
        
        if (!$compatible) {
            $this->warningCount++;
            return '⚠️ Review';
        }
        
        return '✅ Compatible';
    }
    
    /**
     * Write report footer
     * 
     * @return void
     */
    private function writeFooter(): void
    {
        $footer = "## Summary\n\n";
        $footer .= "- **Total Dependencies:** " . ($this->phpCount + $this->jsCount) . "\n";
        $footer .= "- **PHP Dependencies:** {$this->phpCount}\n";
        $footer .= "- **JavaScript Dependencies:** {$this->jsCount}\n";
        $footer .= "- **Errors:** {$this->errorCount}\n";
        $footer .= "- **Warnings:** {$this->warningCount}\n\n";
        
        if ($this->errorCount === 0 && $this->warningCount === 0) {
            $footer .= "✅ **Status:** All dependencies are GPL-3.0 compatible\n\n";
        } elseif ($this->errorCount > 0) {
            $footer .= "❌ **Status:** CRITICAL - Issues detected\n\n";
        } else {
            $footer .= "⚠️ **Status:** Some licenses require manual review\n\n";
        }
        
        $footer .= "## GPL-3.0 Compatible Licenses\n\n";
        foreach (self::GPL_COMPATIBLE_LICENSES as $license) {
            $footer .= "- {$license}\n";
        }
        $footer .= "\n";
        
        $footer .= "## Maintenance\n\n";
        $footer .= "**Update JavaScript dependencies:**\n";
        $footer .= "Edit `licences/javascript-dependencies.json`\n\n";
        $footer .= "**Run compliance check:**\n";
        $footer .= "```bash\n";
        $footer .= "php licences/compliance-checker.php\n";
        $footer .= "```\n\n";
        
        $footer .= "---\n\n";
        $footer .= "*Auto-generated report - Last updated: " . date('Y-m-d H:i:s') . "*\n";
        
        fwrite($this->reportFile, $footer);
    }
    
    /**
     * Create JavaScript dependencies template
     * 
     * @return void
     */
    private function createJsTemplate(): void
    {
        $template = [
            '_comment' => 'Manually maintain this file with JavaScript/CSS dependencies',
            'last_updated' => date('Y-m-d'),
            'dependencies' => [
                [
                    'name' => 'jQuery',
                    'version' => '3.x',
                    'licenses' => ['MIT'],
                    'homepage' => 'https://jquery.org',
                    'type' => 'JavaScript'
                ],
                [
                    'name' => 'AdminLTE',
                    'version' => '3.x',
                    'licenses' => ['MIT'],
                    'homepage' => 'https://adminlte.io',
                    'type' => 'CSS/JavaScript'
                ]
            ]
        ];
        
        file_put_contents(
            __DIR__ . '/javascript-dependencies.json',
            json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}

// Execute
$checker = new LicenseComplianceChecker();
$checker->run();