<?php

namespace uuf6429\cli2php;

interface Modifiable
{
    /**
     * @param string $newName
     *
     * @return $this
     */
    public function renameTo($newName);

    /**
     * @return $this
     */
    public function ignore();

    /**
     * @param string $pattern
     * @param string|\Closure|array $replacement
     *
     * @return $this
     */
    public function modifySummary($pattern, $replacement);

    /**
     * @param string $oldName
     * @param string $newName
     *
     * @return $this
     */
    public function renameArgTo($oldName, $newName);

    /**
     * @param string $returnExpr
     * @param string $returnType
     * @param string $returnDesc
     *
     * @return $this
     */
    public function setReturn($returnExpr, $returnType, $returnDesc);
}
