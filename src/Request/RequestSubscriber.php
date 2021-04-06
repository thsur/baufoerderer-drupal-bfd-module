<?php
 
namespace Drupal\bfd\Request;
 
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Drupal\node\Entity\Node;
use Drupal\bfd\PathHelper;
use Drupal\bfd\Menu\Toc;
 
class RequestSubscriber implements EventSubscriberInterface {

    /**
     * @var PathHelper
     */
    private $path_helper;

    /**
     * @var Toc
     */
    private $toc;
 
    /**
     * @var Request
     */
    private $request;
 
    /**
     * @return Array
     */
    public static function getSubscribedEvents() {

        return [

            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * Redirect a request.
     *
     * Cf.:
     * - https://www.thirdandgrove.com/redirecting-node-pages-drupal-8
     *
     * For alternative ways to handle, routes, see:
     *
     * a) Dynamic routes
     *
     * - https://symfony.com/doc/current/components/routing.html
     * - https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Routing%21routing.api.php/group/routing/8.2.x
     * - https://www.drupal.org/node/2122201
     * - https://www.drupal.org/docs/8/api/routing-system/altering-existing-routes-and-adding-new-routes-based-on-dynamic-ones
     * - https://www.drupal.org/docs/8/api/routing-system/providing-dynamic-routes
     *
     * b) Path Processor
     *
     * - https://www.drupal.org/forum/support/module-development-and-code-questions/2017-11-20/how-to-declare-unlimited-number-of
     * - https://drupal.stackexchange.com/questions/225116/routing-match-everything
     */
    public function onKernelRequest(GetResponseEvent $event) {

        $request = $event->getRequest();
        $path    = $request->getPathInfo();
      
        // Only redirect on node requests/routes, leave all others alone.
        
        $route = $request->attributes->get('_route');

        if ($route != 'entity.node.canonical') {

            return;
        }

        // Redirect by content type
            
        $node = $request->attributes->get('node');
        $type = $node->getType();

        if (stripos($path, '/service') !== false) {

            return;
        }

        switch ($type) {

            case 'sub_section_hub':
                $this->redirectSubSectionHubs($node, $event);
                return;
        }
    }

    /**
     * Filter a response.
     */
    public function onKernelResponse(FilterResponseEvent $event) {

        $request  = $event->getRequest();
        $response = $event->getResponse();

        // Only redirect on node requests/routes, leave all others alone.
        
        $route = $request->attributes->get('_route');
        $path  = $request->getPathInfo();

        // Handle 410s (instead of letting the web server handle it 
        // by immediatly returning a 410 on its own).

        $htaccess_410 = $request->server->get('REDIRECT_URL');

        if (trim($htaccess_410, '/') == 410) {

            $response->setStatusCode(Response::HTTP_GONE);
            return;
        }

        // Redirect some 404s

        if ($route == 'system.404') {

            switch (rtrim($path, '/')) {

                case '/ratgeber':
                case '/service/shop':
                case '/service/lexikon':
                    $this->redirect('/', $event);
                    break;
            }
        }

        // Set canonicals, if necessary

        if ($route == 'entity.node.canonical') {

            $headers = $response->headers;
            $node    = $request->attributes->get('node');

            if (empty($headers->get('link'))) {

                $headers->set('Link', '<'.$node->toUrl()->setAbsolute()->toString().'>; rel="canonical"');
            }
        }
    }

    /**
     * Redirect former 2nd level pages to their 1st level parents.
     *  
     * @param  Node  
     * @return void
     */
    protected function redirectSubSectionHubs(Node $node, KernelEvent $event) {

        $target = $this->path_helper->getNodeParent($node);

        if (!$target) {

            return;
        }

        $url      = $target->toUrl()->toString();
        $response = new RedirectResponse($url, Response::HTTP_MOVED_PERMANENTLY);

        $event->setResponse($response);
    }

    /**
     * Redirect paths.
     *  
     * @param  path  
     * @return void
     */
    protected function redirect(string $to_path, KernelEvent $event) {

        $response = new RedirectResponse($to_path, Response::HTTP_MOVED_PERMANENTLY);
        $event->setResponse($response);
    }

    /**
     * @param  Toc
     */
    public function __construct(PathHelper $path_helper, Toc $toc) {

        $this->path_helper = $path_helper;
        $this->toc         = $toc;
    }
}