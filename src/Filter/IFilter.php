<?php
namespace Scancore\Filter;

interface IFilter
{
    /**
     * Проверяет, проходит ли файл через фильтр.
     *
     * @param string $relativePath Относительный путь к файлу
     * @param string $fullPath     Полный путь к файлу
     * @param array  $context      Дополнительный контекст (например, маппинг классов)
     * @return bool
     */
    public function accept(string $relativePath, string $fullPath, array $context = []): bool;
}