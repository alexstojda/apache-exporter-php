<?php
declare(strict_types=1);

namespace ApacheExporter;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

class Exporter
{
    private static $scoreboardLabelMap;

    /**
     * Static init function. Called when file is loaded.
     */
    public static function init()
    {
        self::$scoreboardLabelMap = array();
        self::$scoreboardLabelMap[ord("_")] = "idle";
        self::$scoreboardLabelMap[ord("S")] = "startup";
        self::$scoreboardLabelMap[ord("R")] = "read";
        self::$scoreboardLabelMap[ord("W")] = "reply";
        self::$scoreboardLabelMap[ord("K")] = "keepalive";
        self::$scoreboardLabelMap[ord("D")] = "dns";
        self::$scoreboardLabelMap[ord("C")] = "closing";
        self::$scoreboardLabelMap[ord("L")] = "logging";
        self::$scoreboardLabelMap[ord("G")] = "graceful_stop";
        self::$scoreboardLabelMap[ord("I")] = "idle_cleanup";
        self::$scoreboardLabelMap[ord(".")] = "open_slot";
    }

    /**
     * Echo a Prometheus friendly version of an Apache status page to stdout.
     *
     * @param string $url URL of Apache server-status?auto page
     * @throws \Prometheus\Exception\MetricsRegistrationException
     */
    public static function simple($url = 'http://localhost/server-status?auto')
    {
        $registry = new CollectorRegistry(new InMemory());
        self::export($registry, $url);

        header('Content-type: ' . RenderTextFormat::MIME_TYPE);
        echo (new RenderTextFormat())->render($registry->getMetricFamilySamples());
    }

    /**
     * Report Apache status to a Prometheus CollectorRegistry.
     *
     * @param CollectorRegistry $registry Registry to report to
     * @param string $url URL of Apache server-status?auto page
     * @return array Parsed Apache server-status data
     * @throws \Prometheus\Exception\MetricsRegistrationException
     */
    public static function export($registry, $url = 'http://localhost/server-status?auto')
    {
        $scrapeFailures = $registry->registerCounter(
            'apache',
            'exporter_scrape_failures_total',
            'Number of errors while scraping apache'
        );
        try {
            $apacheStatus = self::getApacheStatus($url);
            $parsed = self::parseApacheStatus($apacheStatus);
            self::updateRegistryWithStatus($registry, $parsed);
            // make sure the stat shows up
            $scrapeFailures->incBy(0);
            return $parsed;
        } catch (ExporterException $exception) {
            $scrapeFailures->inc();
        }
    }

    /**
     * Load parse-able status page from Apache.
     *
     * @param  string $url URL of Apache status page (including ?auto query string)
     * @return string Apache status page contents
     * @throws ExporterException Error loading status
     */
    public static function getApacheStatus($url = 'http://localhost/server-status?auto')
    {
        // set the timeout low in the off chance the server is slow to respond
        $ini = ini_set('default_socket_timeout', "10");
        try {
            $r = file_get_contents($url);
            if (empty($r)) {
                throw new ExporterException("Failed to load status from {$url}");
            }

            return $r;
        } finally {
            if ($ini !== false) {
                ini_set('default_socket_timeout', $ini);
            }
        }
    }

    /**
     * Parse Apache status page into an array.
     *
     * @param  string $status Apache status page (use ?auto to it's parseable)
     * @return array Parsed status.
     */
    public static function parseApacheStatus($status)
    {
        $r = [];

        $lines = explode("\n", $status);
        foreach ($lines as $line) {
            if (!empty($line)) {
                $explodedLine = explode(': ', $line);
                if (count($explodedLine) == 2) {
                    [$key, $value] = $explodedLine;
                    $r[$key] = $value;
                }
            }
        }

        return $r;
    }

    /**
     * Update a Prometheus CollectorRegistry with stats from an Apache status page
     *
     * @param $registry CollectorRegistry Registry to update
     * @param $status array Parsed Apache status page
     * @throws \Prometheus\Exception\MetricsRegistrationException
     */
    public static function updateRegistryWithStatus($registry, $status)
    {
        // accesses, kbytes and uptime should us a set() or reset() method instead of incBy(),
        // but that's not supported by prometheus_client_php.
        // See https://github.com/Jimdo/prometheus_client_php/issues/68
        $accesses = $registry->registerCounter('apache', 'accesses_total', 'Current total apache accesses');
        $accesses->incBy($status['Total Accesses']);

        $kbytes = $registry->registerCounter('apache', 'sent_kilobytes_total', 'Current total kbytes sent');
        $kbytes->incBy($status['Total kBytes']);

        $uptime = $registry->registerCounter('apache', 'uptime_seconds_total', 'Current uptime in seconds');
        $uptime->incBy($status['Uptime']);

        $workers = $registry->registerGauge('apache', 'workers', 'Apache worker statuses', ['status']);
        $workers->set($status['BusyWorkers'], ['busy']);
        $workers->set($status['IdleWorkers'], ['idle']);

        $scoreboard = $registry->registerGauge('apache', 'scoreboard', 'Apache scoreboard statuses', ['status']);
        //$counter->set(strlen($status['Scoreboard']), ['max']); // TODO: can we just add the other stats?
        // count the characters in the scoreboard
        $charCounts = count_chars($status['Scoreboard']);
        // set a counter for each label
        foreach (self::$scoreboardLabelMap as $char => $label) {
            $scoreboard->set($charCounts[$char], [$label]);
        }
    }
}

// I'm not aware of a better way to do this...
// phpcs:disable
Exporter::init();
// phpcs:enable
