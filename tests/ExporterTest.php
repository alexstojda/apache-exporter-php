<?php
declare(strict_types=1);

namespace ApacheExporter;

use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\APC;

// test double
function file_get_contents($url)
{
    global $sampleApacheStatus;
    if ($url === 'http://fail') {
        return false;
    }

    return $sampleApacheStatus;
}

final class ExporterTest extends TestCase
{
    private $samplePrometheus;

    public function setUp()
    {
        global $sampleApacheStatus;
        $sampleApacheStatus = 'Total Accesses: 1
Total kBytes: 1
Uptime: 18
ReqPerSec: .0555556
BytesPerSec: 56.8889
BytesPerReq: 1024
BusyWorkers: 1
IdleWorkers: 5
Scoreboard: W_____..................................................................................
';
        $this->samplePrometheus = '# HELP apache_accesses_total Current total apache accesses
# TYPE apache_accesses_total gauge
apache_accesses_total 1
# HELP apache_exporter_scrape_failures_total Number of errors while scraping apache
# TYPE apache_exporter_scrape_failures_total counter
apache_exporter_scrape_failures_total 0
# HELP apache_scoreboard Apache scoreboard statuses
# TYPE apache_scoreboard gauge
apache_scoreboard{status="closing"} 0
apache_scoreboard{status="dns"} 0
apache_scoreboard{status="graceful_stop"} 0
apache_scoreboard{status="idle"} 5
apache_scoreboard{status="idle_cleanup"} 0
apache_scoreboard{status="keepalive"} 0
apache_scoreboard{status="logging"} 0
apache_scoreboard{status="open_slot"} 82
apache_scoreboard{status="read"} 0
apache_scoreboard{status="reply"} 1
apache_scoreboard{status="startup"} 0
# HELP apache_sent_kilobytes_total Current total kbytes sent
# TYPE apache_sent_kilobytes_total gauge
apache_sent_kilobytes_total 1
# HELP apache_uptime_seconds_total Current uptime in seconds
# TYPE apache_uptime_seconds_total gauge
apache_uptime_seconds_total 18
# HELP apache_workers Apache worker statuses
# TYPE apache_workers gauge
apache_workers{status="busy"} 1
apache_workers{status="idle"} 5
';
    }

    public function testCanDownloadStats()
    {
        global $sampleApacheStatus;
        try {
            $actual = Exporter::getApacheStatus();
            $this->assertEquals($sampleApacheStatus, $actual);
        } catch (ExporterException $unexpected) {
            $this->fail('Should not have thrown exception');
        }
    }

    public function testDownloadThrowsOnFailure()
    {
        try {
            Exporter::getApacheStatus('http://fail');
            $this->fail('Should have thrown an exception');
        } catch (ExporterException $expected) {
            $this->assertContains('Failed to load', $expected->getMessage());
        }
    }

    public function testShouldParseStats()
    {
        $actual = Exporter::parseApacheStatus("foo: 1\nbar: 2\n");
        $this->assertEquals(['foo' => 1, 'bar' => 2], $actual);
    }

    public function testShouldExportStats()
    {
        $adapter = new InMemory();
        $registry = new CollectorRegistry($adapter);
        Exporter::export($registry);

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());
        $this->assertEquals($this->samplePrometheus, $result);
    }

    public function testShouldExportStatsToAPC()
    {
        $adapter = new APC();
        $registry = new CollectorRegistry($adapter);
        Exporter::export($registry);

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());
        $this->assertEquals($this->samplePrometheus, $result);
    }

    public function testShouldExportStatsToAPCIdempotently()
    {
        $adapter = new APC();
        $registry = new CollectorRegistry($adapter);
        Exporter::export($registry);

        $adapter2 = new APC();
        $registry2 = new CollectorRegistry($adapter2);
        Exporter::export($registry2);

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry2->getMetricFamilySamples());
        $this->assertEquals($this->samplePrometheus, $result);
    }

    public function testShouldCountScrapeFailures()
    {
        $adapter = new InMemory();
        Exporter::export(new CollectorRegistry($adapter), 'http://fail');
        Exporter::export(new CollectorRegistry($adapter), 'http://fail');
        Exporter::export(new CollectorRegistry($adapter), 'http://fail');

        $renderer = new RenderTextFormat();
        $result = $renderer->render((new CollectorRegistry($adapter))->getMetricFamilySamples());
        $this->assertEquals('# HELP apache_exporter_scrape_failures_total Number of errors while scraping apache
# TYPE apache_exporter_scrape_failures_total counter
apache_exporter_scrape_failures_total 3
', $result);
    }
}
