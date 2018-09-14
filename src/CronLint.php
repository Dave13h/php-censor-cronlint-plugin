<?php

namespace PHPCensor\Plugin;

use PHPCensor;
use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildError;
use PHPCensor\Plugin;
use PHPCensor\ZeroConfigPluginInterface;

use hollodotme\CrontabValidator\CrontabValidator;

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

        $this->validator = new CrontabValidator();
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

        $this->validator = $validator = new CrontabValidator();

        if (empty($this->files)) {
            return true;
        }

        foreach ($this->files as $fileName) {
            $cronFile = sprintf('%s%s', $this->buildDir, $fileName);
            printf("%s\n", $cronFile);

            if (!$this->validateFile($cronFile)) {
                continue;
            }

            $cronTab = file_get_contents($cronFile);
            $cronLines = explode("\n", $cronTab);
            foreach ($cronLines as $line) {
                if (!$this->validateLine($line)) {
                    continue;
                }
            }
        }

        return true;
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
        if (!file_exists($file)) {
            $this->build->reportError(
                $this->builder,
                self::pluginName(),
                'Missing Cron File',
                BuildError::SEVERITY_NORMAL,
                $fileName
            );

            return false;
        }

        return true;
    }

    /**
     * Validate Cron Line.
     *
     * @param  string $line
     * @return bool
     */
    protected function validateLine(string $line) : bool
    {
        if (!$this->validator->isIntervalValid($line)) {
            $this->build->reportError(
                $this->builder,
                self::pluginName(),
                sprintf('Invalid expression - %s', $line),
                BuildError::SEVERITY_HIGH,
                $fileName
            );
            return false;
        }

        return true;
    }
}
