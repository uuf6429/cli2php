<?php

namespace uuf6429\cli2php;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class Generator implements Modifiable
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
     * Default data type when not explicitly known.
     *
     * @var string
     */
    private $defaultListType = 'string[]';

    /**
     * @var MethodReturn What methods should return by default.
     */
    private $defaultReturn;

    /**
     * @var string
     */
    private static $COMMAND_PATH_PROP = 'command_path';

    /**
     * @var null|MethodMod
     */
    private $currentMethod;

    /**
     * @var MethodMod[]
     */
    private $methodModifications = [];

    /**
     * @param string $binCmd
     * @param LoggerInterface $logger
     *
     * @return static
     */
    public static function create($binCmd, LoggerInterface $logger = null)
    {
        return new static($binCmd, $logger);
    }

    /**
     * @param string $binCmd
     * @param LoggerInterface $logger
     */
    protected function __construct($binCmd, LoggerInterface $logger = null)
    {
        $this->binCmd = $binCmd;
        $this->logger = $logger ?: new NullLogger();
        $this->defaultReturn = new MethodReturn();
        $this->defaultReturn->expr = '$this';
        $this->defaultReturn->type = '$this';
        $this->defaultReturn->desc = 'current instance, for method chaining';
    }

    /**
     * @return array[] Array of generated methods, key is the command and the value is an array of lines of code.
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
     * @param string $name
     *
     * @return $this
     */
    public function method($name)
    {
        if (!isset($this->methodModifications[$name])) {
            $this->methodModifications[$name] = new MethodMod($name);
        }

        $this->currentMethod = &$this->methodModifications[$name];

        return $this;
    }

    public function renameTo($newName)
    {
        return $this->proxyMethodCall(__FUNCTION__, func_get_args());
    }

    public function ignore()
    {
        return $this->proxyMethodCall(__FUNCTION__, func_get_args());
    }

    public function modifySummary($pattern, $replacement)
    {
        return $this->proxyMethodCall(__FUNCTION__, func_get_args());
    }

    public function renameArgTo($oldName, $newName)
    {
        return $this->proxyMethodCall(__FUNCTION__, func_get_args());
    }

    public function setReturn($returnExpr, $returnType, $returnDesc)
    {
        return $this->proxyMethodCall(__FUNCTION__, func_get_args());
    }

    /**
     * @param string $mtd
     * @param array $args
     *
     * @return $this
     */
    private function proxyMethodCall($mtd, array $args)
    {
        if (!$this->currentMethod) {
            throw new \LogicException('A method must have been selected first, by using method().');
        }

        call_user_func_array([$this->currentMethod, $mtd], $args);

        return $this;
    }

    /**
     * @param string[]|string $cmdPathOrMethod
     *
     * @return null|MethodMod
     */
    private function getMethodMod($cmdPathOrMethod)
    {
        if (is_array($cmdPathOrMethod)) {
            $cmdPathOrMethod = $this->makeSymbolName($cmdPathOrMethod);
        }

        return isset($this->methodModifications[$cmdPathOrMethod])
            ? $this->methodModifications[$cmdPathOrMethod] : null;
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
        $methodMod = $commandPath ? $this->getMethodMod($commandPath) : null;

        if (!$methodMod || !$methodMod->isIgnored()) {
            $builder = $this->getProcessBuilder()->add('help');
            array_map([$builder, 'add'], $commandPath);
            $process = $builder->getProcess();

            $this->processes[]                   = $process;
            $process->{self::$COMMAND_PATH_PROP} = $commandPath;
            $process->start();
        }
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
                    new \Docopt\Handler();
                    $usage = \Docopt\parse_section('usage:', $output);
                    $options = \Docopt\parse_defaults($output);
                    print_r(['$usage'=>$usage,'$options'=>$options]);
                    die('????');


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
        if (!$commandPath) {
            return;
        }

        if (!preg_match('/Usage:\s+(.+?)\\n(.*?)\\n[^\\n]*:\\n/s', $output, $matches)) {
            $this->logger->warning('Could not retrieve summary for "{cmd}" command.', ['cmd' => implode(' ', $commandPath)]);

            return;
        }

        $methodMod = $this->getMethodMod($commandPath);
        $methodName = $methodMod ? $methodMod->getName() : $this->makeSymbolName($commandPath);
        $syntax = $this->parseSyntax(trim($matches[1]), $commandPath);
        $summary = $methodMod ? $methodMod->applySummaryMods(trim($matches[2])) : trim($matches[2]);

        $options = [];
        $parts = (array) preg_split('/(\\w+:)\\n/', $output, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $key => $part) {
            if ($part === 'Options:') {
                $options = $this->parseOptions($parts[$key + 1]);
                break;
            }
        }

        $return = ($methodMod ? $methodMod->getMethodReturn() : null) ?: $this->defaultReturn;

        $this->updateVarNames($syntax, $options, $methodMod);
        $this->generateMethod($methodName, $commandPath, $syntax, $summary, $options, $return);
    }

    /**
     * @param CliSyntax $syntax
     * @param CliOption[] $options
     * @param MethodMod $methodMod
     */
    private function updateVarNames(CliSyntax &$syntax, array &$options, MethodMod $methodMod = null)
    {
        $usedNames = [];

        foreach ($syntax->tokens as &$token) {
            $varName = $this->makeUniqueName($this->makeSymbolName($token->name), $usedNames);
            $usedNames[] = $varName;
            $token->varName = '$' . ($methodMod ? $methodMod->getArgName($varName) : $varName);
        }

        foreach ($options as &$option) {
            $varName = $this->makeUniqueName($this->makeSymbolName($option->name), $usedNames);
            $usedNames[] = $varName;
            $option->varName = '$' . ($methodMod ? $methodMod->getArgName($varName) : $varName);
        }
    }

    /**
     * @param string $name
     * @param string[] $usedNames
     *
     * @return string
     */
    private function makeUniqueName($name, $usedNames)
    {
        $newName = $name;
        $counter = 0;

        while (in_array($newName, $usedNames)) {
            $newName = $name . (++$counter);
        }

        return $newName;
    }

    /**
     * @param string $rawSyntaxLine
     * @param string[] $commandPath
     *
     * @return CliSyntax
     */
    private function parseSyntax($rawSyntaxLine, $commandPath)
    {
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
            $token->dataType = $token->isRepeatable ? $this->defaultListType : $this->defaultType;

            if ($token->isRepeatable && isset($tokens[$token->name])) {
                $tokens[$token->name]->isRepeatable = true;
                $tokens[$token->name]->dataType = $this->defaultListType;
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
     * @param string $methodName
     * @param string[] $commandPath
     * @param CliSyntax $syntax
     * @param string $summary
     * @param CliOption[] $options
     * @param MethodReturn $return
     */
    private function generateMethod($methodName, $commandPath, $syntax, $summary, $options, $return)
    {
        $lines = [];

        $lines[] = '/**';
        foreach (explode("\n", $summary) as $line) {
            $lines[] = ' * ' . trim($line);
        }
        $lines[] = ' *';
        foreach ($syntax->tokens as $token) {
            if ($token->isOptions) {
                // handle options
                foreach ($options as $option) {
                    $lines[] = sprintf(
                        ' * @param %s %s %s',
                        $option->args ? "null|{$this->defaultType}" : 'null|bool',
                        $option->varName,
                        $option->desc
                    );
                }
            } else {
                // handle arguments
                $lines[] = sprintf(
                    ' * @param %s %s',
                    $token->isOptional ? "null|$token->dataType" : $token->dataType,
                    $token->varName
                );
            }
        }
        $lines[] = ' *';
        $lines[] = " * @return {$return->type} {$return->desc}";
        $lines[] = ' *';
        $lines[] = " * {@internal CLI Syntax: {$syntax->source}}";
        $lines[] = ' */';

        $args = [];
        $optionsSet = false;
        foreach ($syntax->tokens as $token) {
            if ($token->isOptions) {
                // handle options
                foreach ($options as $option) {
                    $args[] = $option->varName . ' = null';
                }
                $optionsSet = true;
            } else {
                // handle arguments
                $args[] = $token->varName . (($token->isOptional || $optionsSet) ? ' = null' : '');
            }
        }

        $lines[] = sprintf('public function %s(%s)', $methodName, implode(', ', $args));
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
                    $lines[] = "    if ({$option->varName} !== null) {";
                    $lines[] = sprintf(
                        '        $builder->add(\'%s\')%s;',
                        $option->short ?: $option->long,
                        $option->args ? "->add({$option->varName})" : ''
                    );
                    $lines[] = '    }';
                    $lines[] = '';
                }
            } else {
                // handle arguments
                if ($token->isRepeatable) {
                    $lines[] = "    array_map([\$builder, 'add'], (array){$token->varName});";
                    $lines[] = '';
                } else {
                    if ($token->isOptional) {
                        $lines[] = "    if ({$token->varName} !== null) {";
                        $lines[] = "        \$builder->add({$token->varName});";
                        $lines[] = '    }';
                        $lines[] = '';
                    } else {
                        $lines[] = "    \$builder->add({$token->varName});";
                        $lines[] = '';
                    }
                }
            }
        }

        $lines[] = '    $process = $builder->getProcess();';
        $lines[] = '';
        $lines[] = '    $this->logger->debug(\'RUN \' . $process->getCommandLine());';
        $lines[] = '';
        $lines[] = '    $process->mustRun($this->outputHandler);';
        $lines[] = '';
        $lines[] = "    return {$return->expr};";
        $lines[] = '}';
        $lines[] = '';

        $this->methods[$methodName] = $lines;
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
