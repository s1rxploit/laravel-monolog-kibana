<?php

namespace Pdffiller\LaravelMonologKibana;

use Monolog\Formatter\NormalizerFormatter;

/**
 * Serializes a log message to custom Logstash Event Format
 *
 * @see http://logstash.net/
 * @see https://github.com/logstash/logstash/blob/master/lib/logstash/event.rb
 *
 * @author Anton Balandin <anbalandin@gmail.com>
 */
class KibanaFormatter extends NormalizerFormatter
{
    const APP_TYPE = 'PHP';

    /**
     * @var string the name of the system for the Logstash log message, used to fill the @source field
     */
    protected $systemName;

    /**
     * @var string an application name for the Logstash log message, used to fill the @type field
     */
    protected $applicationName;

    /**
     * @var string a prefix for 'extra' fields from the Monolog record (optional)
     */
    protected $extraPrefix;

    /**
     * @var string a prefix for 'context' fields from the Monolog record (optional)
     */
    protected $contextPrefix;

    /**
     * @param string $applicationName the application that sends the data, used as the "type" field of logstash
     * @param string $systemName      the system/machine name, used as the "source" field of logstash, defaults to the hostname of the machine
     * @param string $extraPrefix     prefix for extra keys inside logstash "fields"
     * @param string $contextPrefix   prefix for context keys inside logstash "fields", defaults to ctxt_
     */
    public function __construct($applicationName, $systemName = null, $extraPrefix = null, $contextPrefix = 'ctxt_')
    {
        // logstash requires a ISO 8601 format date with optional millisecond precision.
        parent::__construct('Y-m-d\TH:i:sP');

        $this->systemName = $systemName ?: gethostname();
        $this->applicationName = $applicationName;
        $this->extraPrefix = $extraPrefix;
        $this->contextPrefix = $contextPrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $record = parent::format($record);

        $message = $this->prepareMessage($record);

        return $this->toJson($message) . "\n";
    }

    /**
     * @param array $record
     * @return array
     */
    protected function prepareMessage(array $record)
    {
        if (empty($record['datetime'])) {
            $record['datetime'] = gmdate('c');
        }
        $message = array(
            '@timestamp' => $record['datetime'],
            'fields' => array()
        );
        
        $fields['AppType'] = self::APP_TYPE;

        if ($this->applicationName) {
            $fields['AppName'] = $this->applicationName;
        }

        $fields['ServerName'] = gethostname();

        if (isset($record['level'])) {
            $fields['Severity'] = $this->rfc5424ToSeverity($record['level']);
        }

        if (isset($_SERVER['PHP_SELF'])) {
            $fields['Server']['scriptName'] = $_SERVER['PHP_SELF'];
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            $fields['Server']['httpHost'] = $_SERVER['HTTP_HOST'];
        }

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $fields['Server']['ip'] = $_SERVER['REMOTE_ADDR'];
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $fields['Server']['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
        }

        if (isset($_SERVER['REQUEST_URI'])) {
            $fields['Server']['requestUri'] = $_SERVER['REQUEST_URI'];
        }

        if (isset($_SERVER['REQUEST_METHOD'])) {
            $fields['Server']['requestMethod'] = $_SERVER['REQUEST_METHOD'];
        }

        if (isset($_SESSION)) {
            $fields['Server']['SESSION'] = $_SESSION;
        }

        if (isset($record['context']['user_name'])) {
            $fields['user_name'] = $record['context']['user_name'];
            unset($record['context']['user_name']);
        }

        if (isset($record['context']['user_id'])) {
            $fields['user_id'] = $record['context']['user_id'];
            unset($record['context']['user_id']);
        }

        if (isset($record['context']['project_id'])) {
            $fields['project_id'] = $record['context']['project_id'];
            unset($record['context']['project_id']);
        }

        $fields['Debug'] = [];
        if (isset($record['message'])) {
            if (strpos($record['message'], 'Pipeline') !== false) {
                $record['message'] = substr($record['message'], 0, strpos($record['message'], 'Pipeline'));
                if (strrpos($record['message'], '#') !== false){
                    $record['message'] = substr($record['message'], 0, strrpos($record['message'], '#'));
                }
            }
            $fields['Debug']['message'] = $record['message'];
        }
        if (!empty($record['context'])) {
            foreach($record['context'] as $key => $value) {
                $fields['Debug'][$key] = $value;
            }
        }

        $message['fields'] = $fields;

        return $message;
    }

    /**
     * @param int $level
     * @return array
     */
    private function rfc5424ToSeverity($level)
    {
        $levels = [
            100 => ['name' => 'Debugging', 'code' => 7],
            200 => ['name' => 'Informational', 'code' => 6],
            250 => ['name' => 'Notice', 'code' => 5],
            300 => ['name' => 'Warning', 'code' => 4],
            400 => ['name' => 'Error', 'code' => 3],
            500 => ['name' => 'Critical', 'code' => 2],
            550 => ['name' => 'Alert', 'code' => 1],
            600 => ['name' => 'Emergency', 'code' => 0]
        ];
        $result = isset($levels[$level]) ? $levels[$level] : $levels[600];

        return $result;
    }
}
