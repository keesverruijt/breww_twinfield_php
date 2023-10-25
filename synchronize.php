<html>
<body>
<?php

require __DIR__ . '/vendor/autoload.php';

use Monolog\Formatter\HtmlFormatter;
use Monolog\Level;
use Monolog\Logger;

$getOpt = new \GetOpt\GetOpt([]);
$getOpt->process();

$level = Level::Info;

ob_start();

class HtmlHandler extends \Monolog\Handler\AbstractProcessingHandler {

	var $body;

	function write(\Monolog\LogRecord $record): void
	{
		#print_r($record);
		echo str_repeat(" ", 4096);
		echo $record->formatted;
		ob_flush();
	}
}

class MyHtmlFormatter extends \Monolog\Formatter\NormalizerFormatter {
    protected function getLevelColor(\Monolog\Level $level): string
    {
        return match ($level) {
            Level::Debug     => '#CCCCCC',
            Level::Info      => '#28A745',
            Level::Notice    => '#17A2B8',
            Level::Warning   => '#FFC107',
            Level::Error     => '#FD7E14',
            Level::Critical  => '#DC3545',
            Level::Alert     => '#821722',
            Level::Emergency => '#000000',
        };
    }

     /**
     * Creates an HTML table cell
     *
     * @param string $td       Row standard cell content
     */
    protected function addCell(string $td, ?string $color = null): string
    {
	if (isset($color)) {
	    $style = ' style="color: ' . $color . '"';
	} else {
            $style = '';
	}
        $td = '<pre>'.htmlspecialchars($td, ENT_NOQUOTES, 'UTF-8').'</pre>';

        return "<td$style>" . $td . "</td>";
    }


    /**
     * Formats a log record.
     *
     * @return string The formatted record
     */
    public function format(\Monolog\LogRecord $record): string
    {
        $output = "<tr>";
	$output .= $this->addCell($record->level->getName(), $this->getLevelColor($record->level));

        $output .= $this->addCell($record->message);
        $output .= $this->addCell($this->formatDate($record->datetime));

	$output .= "</tr>";

        return $output;
    }

        /**
     * Formats a set of log records.
     *
     * @return string The formatted set of records
     */
    public function formatBatch(array $records): string
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }

        return $message;
    }

    /**
     * @param mixed $data
     */
    protected function convertToString($data): string
    {
        if (null === $data || is_scalar($data)) {
            return (string) $data;
        }

        $data = $this->normalize($data);

        return Utils::jsonEncode($data, JSON_PRETTY_PRINT | Utils::DEFAULT_JSON_FLAGS, true);
    }
}


$log = new Monolog\Logger('sync');
$handler = new HtmlHandler($level);
$handler->setFormatter(new MyHtmlFormatter());
$log->pushHandler($handler);

echo "<table>\n";
try {
  include 'sync.inc';
} catch (Exception $e) {
	$log->critical($e->getMessage());
}
$log->close();
echo "</table>\n";
?>
</body>
</html>
