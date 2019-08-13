<?php

namespace PHPCensor\Plugin;

use PHPCensor;
use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildError;
use PHPCensor\Plugin;
use PHPCensor\ZeroConfigPluginInterface;

/**
 * Crontab Linter.
 *
 * @author David Sloan <dave@d3r.com>
 */
class CronLint extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var string
     */
    protected $buildDir;

    /**
     * @var string[] files to lint
     */
    protected $files = [];

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'cron_lint';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->buildDir = $this->builder->buildPath;

        $this->files = [];
        if (isset($options['files']) && is_array($options['files'])) {
            $this->files = $options['files'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecute($stage, Builder $builder, Build $build)
    {
        return ($stage == Build::STAGE_TEST);
    }

    /**
    * Lint each of the files from the options.
    */
    public function execute()
    {
        // $this->builder->logExecOutput(false);
        //

        if (empty($this->files)) {
            return true;
        }

        $success = true;

        foreach ($this->files as $fileName) {
            $cronFile = sprintf('%s%s', $this->buildDir, $fileName);
            if (!$this->validateFile($cronFile)) {
                $this->build->reportError(
                    $this->builder,
                    self::pluginName(),
                    sprintf('Missing Cron File: %s', $fileName),
                    BuildError::SEVERITY_NORMAL,
                    $fileName
                );
                continue;
            }

            $lineNo    = 1;
            $cronTab   = file_get_contents($cronFile);
            $cronLines = explode("\n", $cronTab);
            foreach ($cronLines as $line) {
                $errors = $this->validateLine($line);
                foreach ($errors as $err) {
                    $this->build->reportError(
                        $this->builder,
                        self::pluginName(),
                        $err,
                        BuildError::SEVERITY_HIGH,
                        $fileName,
                        $lineNo
                    );
                    $success = false;
                }
                ++$lineNo;
            }
        }

        return $success;
    }

    // ------------------------------------------------------------------------
    // Validation Checks
    // ------------------------------------------------------------------------
    /**
     * Validate that the file exists.
     *
     * @param  string $file
     * @return bool
     */
    protected function validateFile(string $file) : bool
    {
        return file_exists($file);
    }

    /**
     * Validate Cron Line.
     *
     * @param  string $line
     * @return array
     */
    protected function validateLine(string $line) : array
    {
        $errors = [];

        // Skip comment lines or empty lines
        if (empty($line) || substr($line, 0, 1) == '#') {
            return $errors;
        }

        $line = str_replace("\t", " ", $line);
        $args = array_values(
            array_filter(
                explode(" ", $line),
                function ($v) {
                    return (bool) strlen($v);
                }
            )
        );
        $cmd  = implode(' ', array_slice($args, 5));
        list($mins, $hours, $dayofmonth, $month, $dayofweek) = array_slice($args, 0, 5);

        $regEx = [
            'minhour'     => '/^([\*|\d])$|^([\*|\d]+?(\-\d+))$|^([\*]\/\d+)$|^([\d+]\/\d+?(\-\d+))$|^(\d+-\d+\/[\d]+)$/i',
            'daymonth'    => '/^(\d|\*)$/i',
            'month'       => '/^(\d|\*)$/i',
            'dayweek'     => '/^(\*|\d|[a-z]{3})$/i',
            'cmdoverflow' => '/^(\d|\*)$/i'
        ];


        $offset = 0;
        $mins = explode(',', $mins);
        foreach ($mins as $min) {
            if (!preg_match($regEx['minhour'], $min)) {
                $errors[] = sprintf("Minute[%d] invalid value: %s", $offset, $min);
            }
            ++$offset;
        }

        $offset = 0;
        $hours = explode(',', $hours);
        foreach ($hours as $hour) {
            if (!preg_match($regEx['minhour'], $hour)) {
                $errors[] = sprintf("Hour[%d] invalid value: %s", $offset, $hour);
            }
            ++$offset;
        }

        $offset = 0;
        $dayofmonth = explode(',', $dayofmonth);
        foreach ($dayofmonth as $dom) {
            if (!preg_match($regEx['daymonth'], $dom)) {
                $errors[] = sprintf("Day of month[%d] invalid value: %s", $offset, $dom);
            }
            ++$offset;
        }

        $offset = 0;
        $dayofweek = explode(',', $dayofweek);
        foreach ($dayofweek as $dow) {
            if (!preg_match($regEx['dayweek'], $dow)) {
                $errors[] = sprintf("Day of week[%d] invalid value: %s", $offset, $dow);
            }
            ++$offset;
        }

        if (preg_match($regEx['cmdoverflow'], substr($cmd, 0, 1) == '*')) {
            $errors[] = sprintf("Cmd starts with invalid character: %s", $cmd);
        }

        return $errors;
    }
}
