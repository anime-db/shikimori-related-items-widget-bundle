<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\ShikimoriRelatedItemsWidgetBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityRepository;
use AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser;
use AnimeDb\Bundle\ShikimoriFillerBundle\Service\Filler;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Widget\Item as ItemWidget;

/**
 * Similar items widget
 *
 * @package AnimeDb\Bundle\ShikimoriRelatedItemsWidgetBundle\Controller
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class WidgetController extends Controller
{
    /**
     * API path for get related items
     *
     * @var string
     */
    const PATH_RELATED_ITEMS = '/animes/#ID#/related';

    /**
     * Cache lifetime 1 week
     *
     * @var integer
     */
    const CACHE_LIFETIME = 604800;

    /**
     * New items
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Item $item, Request $request)
    {
        $response = new Response();
        $response->setMaxAge(self::CACHE_LIFETIME);
        $response->setSharedMaxAge(self::CACHE_LIFETIME);
        $response->setExpires((new \DateTime())->modify('+'.self::CACHE_LIFETIME.' seconds'));

        // update cache if app update and Etag not Modified
        if ($last_update = $this->container->getParameter('last_update')) {
            $response->setLastModified(new \DateTime($last_update));
        }
        // item last update
        if ($response->getLastModified() < $item->getDateUpdate()) {
            $response->setLastModified($item->getDateUpdate());
        }

        /* @var $widget \AnimeDb\Bundle\ShikimoriWidgetBundle\Service\Widget */
        $widget = $this->get('anime_db.shikimori.widget');

        // get shikimori item id
        if (!($item_id = $widget->getItemId($item))) {
            return $response;
        }

        $list = $this->get('anime_db.shikimori.browser')
            ->get(str_replace('#ID#', $item_id, self::PATH_RELATED_ITEMS));

        // create Etag by list items
        $ids = '';
        if ($list) {
            $tmp = [];
            foreach ($list as $item) {
                if ($item['anime']) {
                    $ids = ':'.$item['anime']['id'];
                    $tmp[] = $item;
                }
            }
            $list = $tmp;
        }
        $response->setEtag(md5($ids));

        // response was not modified for this request
        if ($response->isNotModified($request) || !$list) {
            return $response;
        }

        // build list item entities
        foreach ($list as $key => $item) {
            $list[$key] = $widget->getWidgetItem($widget->getItem($item['anime']['id']));
            // add relation
            if (substr($this->container->getParameter('locale'), 0, 2) == 'ru') {
                $list[$key]->setName($list[$key]->getName().' ('.$item['relation_russian'].')');
            } else {
                $list[$key]->setName($list[$key]->getName().' ('.$item['relation'].')');
            }
        }

        return $this->render(
            'AnimeDbShikimoriRelatedItemsWidgetBundle:Widget:index.html.twig',
            ['items' => $list],
            $response
        );
    }
}