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
     * API path for get item info
     *
     * @var string
     */
    const PATH_ITEM_INFO = '/animes/#ID#';

    /**
     * RegExp for get item id
     *
     * @var string
     */
    const REG_ITEM_ID = '#/animes/(?<id>\d+)\-#';

    /**
     * World-art item url
     *
     * @var string
     */
    const WORLD_ART_URL = 'http://www.world-art.ru/animation/animation.php?id=#ID#';

    /**
     * MyAnimeList item url
     *
     * @var string
     */
    const MY_ANIME_LIST_URL = 'http://myanimelist.net/anime/#ID#';

    /**
     * AniDB item url
     *
     * @var string
     */
    const ANI_DB_URL = 'http://anidb.net/perl-bin/animedb.pl?show=anime&aid=#ID#';

    /**
     * Cache lifetime 1 day
     *
     * @var integer
     */
    const CACHE_LIFETIME = 86400;

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
        // update cache if app update and Etag not Modified
        if (($last_update = $this->container->getParameter('last_update')) && $request->getETags()) {
            $response->setLastModified(new \DateTime($last_update));
        }
        // check items last update
        /* @var $repository \AnimeDb\Bundle\CatalogBundle\Repository\Item */
        $repository = $this->getDoctrine()->getRepository('AnimeDbCatalogBundle:Item');
        $last_update = $repository->getLastUpdate();
        if ($response->getLastModified() < $last_update) {
            $response->setLastModified($last_update);
        }

        $response->setMaxAge(self::CACHE_LIFETIME);
        $response->setSharedMaxAge(self::CACHE_LIFETIME);
        $response->setExpires((new \DateTime())->modify('+'.self::CACHE_LIFETIME.' seconds'));
        // response was not modified for this request
        if ($response->isNotModified($request)) {
            return $response;
        }

        $etag = $repository->count().':';

        /* @var $browser \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser */
        $browser = $this->get('anime_db.shikimori.browser');

        // get shikimori item id
        /* @var $source \AnimeDb\Bundle\CatalogBundle\Entity\Source */
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), $browser->getHost()) === 0 &&
                preg_match(self::REG_ITEM_ID, $source->getUrl(), $match)
            ) {
                $item_id = $match['id'];
                break;
            }
        }

        if (empty($item_id)) {
            return $response;
        }

        $list = $browser->get(str_replace('#ID#', $item_id, self::PATH_RELATED_ITEMS));
        // create Etag by list items
        if ($list) {
            $ids = [];
            $tmp = [];
            foreach ($list as $item) {
                if ($item['anime']) {
                    $ids[] = $item['anime']['id'];
                    $tmp[] = $item;
                }
            }
            $list = $tmp;
            $etag .= implode(':', $ids);
        }
        $response->setEtag(md5($etag));

        // response was not modified for this request
        if ($response->isNotModified($request) || !$list) {
            return $response;
        }

        $repository = $this->getDoctrine()->getRepository('AnimeDbCatalogBundle:Source');
        $locale = substr($request->getLocale(), 0, 2);
        $filler = null;
        if ($this->has('anime_db.shikimori.filler')) {
            $filler = $this->get('anime_db.shikimori.filler');
        }

        // build list item entities
        foreach ($list as $key => $item) {
            $list[$key] = $this->buildItem($item, $locale, $repository, $browser, $filler);
        }

        return $this->render(
            'AnimeDbShikimoriRelatedItemsWidgetBundle:Widget:index.html.twig',
            ['items' => $list],
            $response
        );
    }

    /**
     * Build item entity
     *
     * @param array $item
     * @param string $locale
     * @param \Doctrine\ORM\EntityRepository $repository
     * @param \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser $browser
     * @param \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Filler $filler
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Widget\Item
     */
    protected function buildItem(
        array $item,
        $locale,
        EntityRepository $repository,
        Browser $browser,
        Filler $filler = null
    ) {
        $entity = new ItemWidget();
        // get item info
        $info = $browser->get(str_replace('#ID#', $item['anime']['id'], self::PATH_ITEM_INFO));

        // set name
        if ($locale == 'ru' && $item['anime']['russian']) {
            $entity->setName($item['anime']['russian']);
        } elseif ($locale == 'ja' && $info['japanese']) {
            $entity->setName($info['japanese'][0]);
        } else {
            $entity->setName($item['anime']['name']);
        }
        if ($locale == 'ru') {
            $entity->setName($entity->getName().' ('.$item['relation_russian'].')');
        } else {
            $entity->setName($entity->getName().' ('.$item['relation'].')');
        }
        $entity->setLink($browser->getHost().$item['anime']['url']);
        $entity->setCover($browser->getHost().$item['anime']['image']['original']);

        // find item by sources
        $sources = [$entity->getLink()];
        if (!empty($info['world_art_id'])) {
            $sources[] = str_replace('#ID#', $info['world_art_id'], self::WORLD_ART_URL);
        }
        if (!empty($info['myanimelist_id'])) {
            $sources[] = str_replace('#ID#', $info['myanimelist_id'], self::MY_ANIME_LIST_URL);
        }
        if (!empty($info['ani_db_id'])) {
            $sources[] = str_replace('#ID#', $info['ani_db_id'], self::ANI_DB_URL);
        }
        /* @var $source \AnimeDb\Bundle\CatalogBundle\Entity\Source|null */
        $source = $repository->findOneByUrl($sources);
        if ($source instanceof Source) {
            $entity->setItem($source->getItem());
        } elseif ($filler instanceof Filler) {
            $entity->setLinkForFill($filler->getLinkForFill($browser->getHost().$item['anime']['url']));
        }

        return $entity;
    }
}