<?php

use Slim\View;

/**
 * Surrounds the view with a base layout.
 */
class LayoutView extends View
{
    private $baseLayoutPath;

    /**
     * Constructor.
     *
     * @param string $baseLayoutPath  Path of the layout to use to surround the view
     * @throws \RuntimeException      If the given file does not exist.
     */
    public function __construct($baseLayoutPath)
    {
        parent::__construct();

        if (!file_exists($baseLayoutPath)) {
            throw new \RuntimeException("Cannot find template `{$baseLayoutPath}`");
        }

        $this->baseLayoutPath = $baseLayoutPath;
    }

    /**
     * Render a template file surrounded by a base template.
     *
     * @param  string $template  The template pathname, relative to the template base directory
     * @param  array  $data      Any additonal data to be passed to the template.
     * @return string            The rendered template
     */
    public function render($template, $data = null)
    {
        extract(array_merge($this->data->all(), [
            'child_view' => parent::render($template, $data),
            'page_title' => $this->get('page_title') ?: null,
        ]));

        ob_start();
        require_once $this->baseLayoutPath;

        return ob_get_clean();
    }
}
