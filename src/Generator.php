<?php

namespace uuf6429\cli2php;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class Generator
{
    /**
     * @var string
     */
    private $binCmd;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string[]
     */
    private $methods;

    /**
     * @var Process[]
     */
    private $processes;

    /**
     * Default data type when not explicitly known.
     *
     * @var string
     */
    private $defaultType = 'string';

    /**
     * @var string
     */
    private static $COMMAND_PATH_PROP = 'command_path';

    /**
     * @param string $binCmd
     * @param LoggerInterface $logger
     */
    public function __construct($binCmd, LoggerInterface $logger)
    {
        $this->binCmd = $binCmd;
        $this->logger = $logger;
    }

    /**
     * @return array|string[]
     */
    public function generate()
    {
        $this->methods = [];
        $this->processes = [];

        $this->processCommand([]);

        while ((bool) ($count = count(array_filter($this->processes)))) {
            $this->logger->debug("Checking $count process(es)...");
            $this->checkProcesses();
        }

        // sort results naturally (by key)
        array_multisort(array_keys($this->methods), SORT_NATURAL, $this->methods);

        return $this->methods;
    }

    /**
     * @return ProcessBuilder
     */
    private function getProcessBuilder()
    {
        return ProcessBuilder::create([$this->binCmd]);
    }

    /**
     * @param string[] $commandPath
     */
    private function processCommand($commandPath)
    {
        $builder = $this->getProcessBuilder()->add('help');
        array_map([$builder, 'add'], $commandPath);
        $process = $builder->getProcess();

        $this->processes[] = $process;
        $process->{self::$COMMAND_PATH_PROP} = $commandPath;
        $process->start();
    }

    private function checkProcesses()
    {
        /**
         * @var int $key
         * @var Process $process
         */
        foreach (array_reverse($this->processes, true) as $key => $process) {
            if ($process && !$process->isRunning()) {
                // process exited, remove it from list
                $this->processes[$key] = null;

                if ($process->getExitCode()) {
                    // process failed for some reason, log error and continue
                    $this->logger->warning(
                        sprintf(
                            "Process exited with status %s:\nCommand: %s\nStdOut: %s\nStdErr: %s",
                            $process->getExitCode(),
                            $process->getCommandLine(),
                            $process->getOutput(),
                            $process->getErrorOutput()
                        )
                    );
                } else {
                    // process successful, parse output, add new commands and inspect them too
                    $this->logger->info("Process $key finished, processing output...");
                    $output = $process->getOutput() ?: $process->getErrorOutput();
                    $this->handleOptions($process->{self::$COMMAND_PATH_PROP}, $output);
                    $this->handleCommands($process->{self::$COMMAND_PATH_PROP}, $output);
                }
            }
        }
    }

    /**
     * @param string[] $commandPath
     * @param string $output
     */
    private function handleOptions($commandPath, $output)
    {
        if (!preg_match('/Usage:\s+(.+?)\\n(.*?)\\n[^\\n]*:\\n/s', $output, $matches)) {
            $this->logger->warning('Could not retrieve summary for "{cmd}" command.', ['cmd' => implode(' ', $commandPath)]);

            return;
        }

        $syntax = $this->parseSyntax(trim($matches[1]), $commandPath);
        $summary = trim($matches[2]);

        $options = [];
        $parts = (array) preg_split('/(\\w+:)\\n/', $output, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $key => $part) {
            if ($part === 'Options:') {
                $options = $this->parseOptions($parts[$key + 1]);
                break;
            }
        }

        $this->generateMethod($commandPath, $syntax, $summary, $options);
    }

    /**
     * @param string $rawSyntaxLine
     * @param string[] $commandPath
     *
     * @return CliSyntax
     */
    private function parseSyntax($rawSyntaxLine, $commandPath)
    {
        static $tokenDataType = [
            'container' => 'string',
        ];

        /** @var CliSyntaxToken[] $tokens */
        $tokens = [];
        $rawSyntaxLine = str_replace(' | ', '_OR_', $rawSyntaxLine);
        $plain = substr(
            explode(' ', $rawSyntaxLine, 2)[1],
            strlen(implode(' ', $commandPath)) + 1
        );

        // FIXME use better parser that respects spaces in quotes and brackets
        foreach (array_filter(explode(' ', $plain)) as $rawToken) {
            $token = new CliSyntaxToken();
            $token->name = rtrim(ltrim(strtolower($rawToken), '['), '].');
            $token->isOptions = $rawToken === '[OPTIONS]';
            $token->isOptional = substr_replace($rawToken, '', 1, -1) === '[]';
            $token->isRepeatable = substr($rawToken, -4, 3) === '...';
            $token->dataType = isset($tokenDataType[$token->name])
                ? $tokenDataType[$token->name] : $this->defaultType;

            if ($token->isRepeatable && isset($tokens[$token->name])) {
                $tokens[$token->name]->isRepeatable = true;
            } else {
                $tokens[$token->name] = $token;
            }
        }

        $syntax = new CliSyntax();
        $syntax->source = $rawSyntaxLine;
        $syntax->plain = $plain;
        $syntax->tokens = array_values($tokens);

        return $syntax;
    }

    /**
     * @param string $rawOptionsSection
     *
     * @return CliOption[]
     */
    private function parseOptions($rawOptionsSection)
    {
        $options = [];
        $lines = explode("\n", $rawOptionsSection);
        // parse lines into options
        foreach ($lines as $line) {
            $regex = '/^\\s{2,}(?:(--?\\w+),)?\\s+(--?\\w+)\\s(\\w+)?\\s{2,}(.+)$/';
            if (preg_match($regex, $line, $matches)) {
                list(, $arg1, $arg2, $args, $desc) = $matches;
                $option = new CliOption();
                $option->short = substr($arg1, 0, 2) == '--' ? $arg2 : $arg1;
                $option->long = substr($arg1, 0, 2) == '--' ? $arg1 : $arg2; // FIXME handle --arg=val
                $option->name = max(ltrim($arg1, '-'), ltrim($arg2, '- '));
                $option->args = $args;
                $option->desc = $desc;
                $options[] = $option;
            } elseif (($count = count($options))) {
                $options[$count - 1]->desc .= ' ' . trim($line);
            }
        }

        // remove useless options
        return array_filter(
            $options,
            function ($option) {
                /** @var CliOption $option */
                return $option->long !== '--help';
            }
        );
    }

    /**
     * @param string[] $commandPath
     * @param CliSyntax $syntax
     * @param string $summary
     * @param CliOption[] $options
     */
    private function generateMethod($commandPath, $syntax, $summary, $options)
    {
        if (!$commandPath) {
            return;
        }

        $lines = [];

        $lines[] = '/**';
        $lines[] = ' * ' . $summary;
        $lines[] = ' *';
        foreach ($syntax->tokens as $token) {
            if ($token->isOptions) {
                // handle options
                foreach ($options as $option) {
                    $lines[] = sprintf(
                        ' * @param %s $%s %s',
                        $option->args ? "null|{$this->defaultType}" : 'null|bool',
                        $this->makeSymbolName($option->name),
                        $option->desc
                    );
                }
            } else {
                // handle arguments
                $lines[] = sprintf(
                    ' * @param %s $%s',
                    $token->isOptional ? "null|$token->dataType" : $token->dataType,
                    $this->makeSymbolName($token->name)
                );
            }
        }
        $lines[] = ' *';
        $lines[] = ' * @return $this current instance, for method chaining';
        $lines[] = ' *';
        $lines[] = " * {@internal CLI Syntax: {$syntax->source}}";
        $lines[] = ' */';

        $args = [];
        $optionsSet = false;
        foreach ($syntax->tokens as $token) {
            if ($token->isOptions) {
                // handle options
                foreach ($options as $option) {
                    $args[] = sprintf(
                        '$%s = null',
                        $this->makeSymbolName($option->name)
                    );
                }
                $optionsSet = true;
            } else {
                // handle arguments
                $args[] = sprintf(
                    '$%s%s',
                    $this->makeSymbolName($token->name),
                    ($token->isOptional || $optionsSet) ? ' = null' : ''
                );
            }
        }

        $lines[] = sprintf(
            'public function %s(%s)',
            $this->makeSymbolName($commandPath),
            implode(', ', $args)
        );
        $lines[] = '{';
        $lines[] = '    $builder = $this->getProcessBuilder();';
        $lines[] = '';
        $lines[] = sprintf(
            '    $builder->add(\'%s\');',
            implode('\')->add(\'', $commandPath)
        );
        $lines[] = '';

        foreach ($syntax->tokens as $token) {
            if ($token->isOptions) {
                // handle options
                foreach ($options as $option) {
                    $varName = '$' . $this->makeSymbolName($option->name);
                    $lines[] = "    if ($varName !== null) {";
                    $lines[] = sprintf(
                        '        $builder->add(\'%s\')%s;',
                        $option->short ?: $option->long,
                        $option->args ? "->add($varName)" : ''
                    );
                    $lines[] = '    }';
                    $lines[] = '';
                }
            } else {
                // handle arguments
                $varName = '$' . $this->makeSymbolName($token->name);
                if ($token->isOptional) {
                    $lines[] = "    if ($varName !== null) {";
                    $lines[] = "        \$builder->add($varName);";
                    $lines[] = '    }';
                    $lines[] = '';
                } else {
                    $lines[] = "    \$builder->add($varName);";
                    $lines[] = '';
                }
            }
        }

        $lines[] = '    $process = $builder->getProcess();';
        $lines[] = '';
        $lines[] = '    $this->logger->debug(\'RUN \' . $process->getCommandLine());';
        $lines[] = '';
        $lines[] = '    $process->mustRun($this->outputHandler);';
        $lines[] = '';
        $lines[] = '    return $this;';
        $lines[] = '}';
        $lines[] = '';

        $this->methods[implode(' ', $commandPath)] = $lines;
    }

    /**
     * @param string[] $commandPath
     * @param string $output
     */
    private function handleCommands($commandPath, $output)
    {
        $offset = 0;
        $pattern = '/^.*Commands:\\r?\\n((  ([\\w-]+)\\s+(.+)\\r?\\n)+)/m';
        while (preg_match($pattern, $output, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $matches[1][1];

            if (preg_match_all('/^  ([\\w-]+)\\s+(.+)$/m', $matches[1][0], $matches)) {
                foreach ($matches[1] as $command) {
                    $this->processCommand(array_merge($commandPath, [trim($command)]));
                }
            }
        }
    }

    /**
     * @param string|string[] $data
     *
     * @return string
     */
    private function makeSymbolName($data)
    {
        if (is_array($data)) {
            $data = implode(' ', $data);
        }

        // generate space-separated camelcase symbol
        $data = lcfirst(ucwords(str_replace(['_', '-'], ' ', $data)));

        // remove spaces and replace special characters with underscore (safe)
        $data = preg_replace('/\W/', '_', str_replace(' ', '', $data));

        if (!$data) {
            throw new \LogicException('Generated symbol name is empty.');
        }

        return $data;
    }
}
