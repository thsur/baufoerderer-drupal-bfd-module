<?php

namespace Drupal\bfd\Content;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;

use Drupal\bfd\Utilities;
use Drupal\bfd\Content\Media;
use Drupal\file\FileInterface;

use Dompdf\Dompdf;
use mikehaertl\wkhtmlto\Pdf as WkPDF;


/**
 * PDF Service
 */
class PDF {

    /**
     * @var Utilities
     */
    private $utils;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var EntityTypeManagerInterface
     */
    private $entityManager;

    /**
     * @var Renderer
     */
    private $renderer;

    /**
     * @var Media
     */
    private $media;

    /**
     * @var Array
     */
    private $options;

    /**
     * @var Array
     */
    private $templates;

    /**
     * Set PDF rendering options.
     *
     * @return void
     */
    protected function setOptions() {

        $this->options = [

            'encoding' => 'UTF-8',
            'disable-smart-shrinking',

            'margin-top'    => 18,
            'margin-left'   => 14,
            'margin-bottom' => 25,
            'margin-right'  => 14,

            'outline-depth' => 0
        ];
    }

    protected function getPath() {

        return drupal_get_path('module', 'bfd');
    }

    /**
     * Set templates.
     *
     * @return void
     */
    protected function setTemplates() {

        $module_path = $this->getPath();

        $this->templates = [

            'node'   => $module_path.'/templates/subsidy-pdf.html.twig',
            'footer' => $module_path.'/templates/subsidy-pdf--footer.html.twig',
        ];
    }

    /**
     * Render a given template.
     * 
     * Cf.:
     * - https://www.jeffgeerling.com/blog/2019/rendering-twig-templates-programmatically-drupal-8
     * 
     * @param  String
     * @param  Array
     * @return String
     */
    protected function renderTemplate(string $template, array $vars = []) {

        $markup = twig_render_template(

            $template,
            array_merge(
            
                ['theme_hook_original' => 'n/a'], // Needed to prevent notices when Twig debugging is enabled.
                $vars
            )
        );

        return $markup->__toString();
    }

    /**
     * Render a node as if it would be part of an ordinary node request,
     * and get its view array and markup.
     *
     * While this renders a node perfectly fine, it does so only OUTSIDE a 
     * normal page request, so better trigger the whole PDF generation
     * process via a Drush command or script.
     * 
     * @param  Int    
     * @return Array 
     */
    protected function renderNode(int $nid): array {

        $node = $this->entityManager->getStorage('node')->load($nid);

        if (!$node) {

            return [];
        }

        // Get node's render array.

        $view = $this->entityManager->getViewBuilder('node')->view($node);

        // Create a node request in order to simulate a node rendering context.
        
        $request  = Request::createFromGlobals(); 
        $request->attributes->add(['node' => $node, 'generate-pdf' => true]);

        // Make our newly created request the current one by appending it to the overall stack of requests.
        // 
        // No need to take the long route by doing e.g. Drupal::service('http_kernel')->handle(Request) and
        // intercepting it along the way.   

        $this->requestStack->push($request);

        // Do the actual rendering.
        
        try {
        
            return [$node, $view, $this->renderer->renderPlain($view)];
        }
        catch (\Exception $e) {
        
            return [];
        }
        finally {

            $this->requestStack->pop();  // Clean up.
        }
    }

    protected function renderPDF(Node $node, string $markup): WkPdf {

        // Update PDF options with rendered header and footer.
        
        $options = array_merge(

            $this->options,
            [

                'footer-html' => $this->renderTemplate(

                    $this->templates['footer'],  
                    ['node' => $node, 'font' => $this->getFontPath()]
                ),
            ]
        );

        $pdf = new WkPdf($markup);
        $pdf->setOptions($options);

        return $pdf;
    }

    protected function getCSS() {

        return file_get_contents(DRUPAL_ROOT.'/'.drupal_get_path('theme', 'vzbv_generic').'/build/main.css');
    }

    protected function getFontPath() {

        return DRUPAL_ROOT.'/'.drupal_get_path('theme', 'vzbv_generic').'/src/fonts/merriweather/merriweather-sans-v10-latin-300.ttf';
    }

    /**
     * Build file name based on URL alias.
     * 
     * @param  Node   
     * @return String
     */
    protected function getFilename(Node $node): string {

        $url = $node->toUrl()->toString();
        return substr($url, strrpos($url, '/') + 1).'.pdf';
    }

    protected function writePDF(WkPdf $pdf, Node $node) {

        $fn    = $this->getFilename($node); 

        $file  = $this->media->createPublicFile($pdf->toString(), "media/pdf/{$fn}");
        $media = $this->media->createMediaEntryFromFile($file, $node->label());

        foreach ($media as $item) {

            $this->media->tagMediaItem($item, [Media::SUBSIDIES]);
        }

        \Drupal::logger('bfd.pdf')->notice('PDF file and db entries for node '.$node->id().' written and updated.');
    }

    /**
     * Create a PDF for the given node id.
     *
     * @return Array
     */
    public function create(int $nid) {

        list($node, $view, $markup) = $this->renderNode($nid);

        if (!$markup) {

            return false;
        }

        /**
         * @var WkPdf
         */
        $pdf = $this->renderPDF(

            $node,
            $this->renderTemplate(

                $this->templates['node'], 
                ['node' => $node, 'content' => $markup, 'css' => $this->getCSS()]
            )
        );

        $this->writePDF($pdf, $node);
    }

    /**
     * Get a PDF file entity plus its media entity for the given node.
     * 
     * @param  Node  
     * @return [Media, FileInterface] | void
     */
    public function get(Node $node): ?array {

        // Get all potential files and filter them instead of
        // querying the db directly.
        // 
        // If numbers grow, this should be improved by replacing
        // it with a query plus caching the results in a lookup
        // table.
        // 
        // @see Checklists.php
        
        $files  = $this->media->getFilesByTags([Media::SUBSIDIES]);
        $lookup = $this->getFilename($node);

        if (empty($files) || !isset($files['items'])) {

            return null;
        }

        foreach ($files['items'] as $item) {

            $media = $item['item']   ?? null;
            $file  = $item['source'] ?? null;
            
            if (!($file instanceof FileInterface)) {

                continue;
            }

            $mime = strtolower($file->getMimeType());
            
            if (strpos($mime, 'pdf') === false) {

                continue;
            }

            if ($file->getFilename() == $lookup) {

                return [$media, $file];
            }
        }

        return null;
    }

    /**
     * @param RequestStack
     */
    public function __construct(Utilities $utils, EntityTypeManagerInterface $entityManager, RequestStack $request, RendererInterface $renderer, Media $media) {

        $this->utils         = $utils;
        $this->entityManager = $entityManager;
        $this->requestStack  = $request;
        $this->renderer      = $renderer;
        $this->media         = $media;

        $this->setOptions();
        $this->setTemplates();
    }
}