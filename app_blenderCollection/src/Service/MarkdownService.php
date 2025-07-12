<?php 
namespace App\Service;

use League\CommonMark\CommonMarkConverter;

class MarkdownService
{
    /**
     * Instance du convertisseur Markdown → HTML.
     *
     * @var CommonMarkConverter
     */
    private CommonMarkConverter $converter;

    /**
     * Initialise le service avec une configuration sécurisée par défaut.
     * - 'html_input' => 'strip' : supprime les balises HTML dans le markdown
     * - 'allow_unsafe_links' => false : empêche les liens potentiellement dangereux (javascript:, etc.)
     */
    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Convertit un contenu Markdown en HTML sécurisé.
     *
     * @param string $markdown Le texte au format Markdown
     * @return string Le HTML généré
     */
    public function toHtml(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
