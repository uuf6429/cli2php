<?php

namespace uuf6429\cli2php;

class MethodMod implements Modifiable
{
    private $name;
    private $origName;
    private $isIgnored = false;
    private $summaryMods = [];
    private $renamedArgs = [];
    private $methodReturn;

    public function __construct($name)
    {
        $this->name = $this->origName = $name;
    }

    public function renameTo($newName)
    {
        $this->origName = $this->name;
        $this->name     = $newName;

        return $this;
    }

    public function ignore()
    {
        $this->isIgnored = true;

        return $this;
    }

    public function modifySummary($pattern, $replacement)
    {
        $this->summaryMods[] = [$pattern, $replacement];

        return $this;
    }

    public function renameArgTo($oldName, $newName)
    {
        $this->renamedArgs[ltrim($oldName, '$')] = ltrim($newName, '$');

        return $this;
    }

    public function setReturn($returnExpr, $returnType, $returnDesc)
    {
        $this->methodReturn = new MethodReturn();
        $this->methodReturn->expr = $returnExpr;
        $this->methodReturn->type = $returnType;
        $this->methodReturn->desc = $returnDesc;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isIgnored()
    {
        return $this->isIgnored;
    }

    /**
     * @param string $summary
     *
     * @return string
     */
    public function applySummaryMods($summary)
    {
        foreach ($this->summaryMods as $mod) {
            list($pattern, $replace) = $mod;
            if (is_string($replace)) {
                $summary = preg_replace($pattern, $replace, $summary);
            } else {
                $summary = preg_replace_callback($pattern, $replace, $summary);
            }
        }

        return $summary;
    }

    /**
     * @param string $oldName
     *
     * @return string
     */
    public function getArgName($oldName)
    {
        return isset($this->renamedArgs[$oldName])
            ? $this->renamedArgs[$oldName] : $oldName;
    }

    /**
     * @return null|MethodReturn
     */
    public function getMethodReturn()
    {
        return $this->methodReturn;
    }
}
