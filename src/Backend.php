<?php
/**
 * @brief Related Entries, a plugin for Dotclear 2
 *
 * @package    Dotclear
 * @subpackage Plugins
 *
 * @author Philippe aka amalgame <philippe@dissitou.org>
 *
 * @copyright GPL-2.0 [https://www.gnu.org/licenses/gpl-2.0.html]
 */

declare(strict_types=1);

namespace Dotclear\Plugin\relatedEntries;

use Dotclear\Core\Backend\UserPref;
use Dotclear\Core\Backend\Utility;
use dcCore;
use Dotclear\Core\Backend\Favorites;
use Dotclear\Core\Process;
use ArrayObject;
use Dotclear\Core\Backend\Filter\Filter;
use form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;

class Backend extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        dcCore::app()->addBehaviors([
            'adminDashboardFavoritesV2' => function (Favorites $favs) {
                $favs->register(My::id(), [
                    'title'       => My::name(),
                    'url'         => My::manageUrl(),
                    'small-icon'  => My::icons(),
                    'large-icon'  => My::icons(),
                    'permissions' => dcCore::app()->auth->makePermissions([
                        dcCore::app()->auth::PERMISSION_ADMIN,
                    ]),
                ]);
            },
        ]);

        My::addBackendMenuItem(Utility::MENU_BLOG);

        if ((isset($_GET['addlinks']) && $_GET['addlinks'] == 1) || (isset($_GET['p']) && $_GET['p'] == 'relatedEntries')) {
            dcCore::app()->addBehavior('adminColumnsListsV2', [BackendBehaviors::class, 'adminColumnsLists']);
            dcCore::app()->addBehavior('adminPostListHeaderV2', [BackendBehaviors::class, 'adminPostListHeader']);
            dcCore::app()->addBehavior('adminPostListValueV2', [BackendBehaviors::class, 'adminPostListValue']);
        }

        dcCore::app()->addBehavior('adminPostFilterV2', [self::class,  'adminPostFilter']);
        dcCore::app()->addBehavior('adminPageHelpBlock', [self::class,  'adminPageHelpBlock']);
        dcCore::app()->addBehavior('adminPostHeaders', [self::class,  'postHeaders']);
        dcCore::app()->addBehavior('adminPostForm', [self::class,  'adminPostForm']);
        dcCore::app()->addBehavior('initWidgets', [Widgets::class, 'initWidgets']);

        if (isset($_GET['id']) && isset($_GET['r_id'])) {
            try {
                $meta  = dcCore::app()->meta;
                $id    = $_GET['id'];
                $r_ids = $_GET['r_id'];

                foreach ($meta->splitMetaValues($r_ids) as $tag) {
                    $meta->delPostMeta($id, 'relatedEntries', $tag);
                    $meta->delPostMeta($tag, 'relatedEntries', $id);
                }

                Http::redirect(dcCore::app()->getPostAdminURL('post', $id, false, ['del' => 1,'upd' => 1]));
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());
            }
        }

        return true;
    }

    public static function adminPostFilter(ArrayObject $filters)
    {
        if (My::url() === dcCore::app()->adminurl->get('admin.plugin.' . My::id())) {
            $categories = null;

            try {
                $categories = dcCore::app()->blog->getCategories(['post_type' => 'post']);
                if ($categories->isEmpty()) {
                    return null;
                }
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());

                return null;
            }

            $my_categories_combo = [
                '-'            => '',
                __('(No cat)') => 'NULL',
            ];
            while ($categories->fetch()) {
                try {
                    $params['no_content'] = true;
                    $params['cat_id']     = $categories->cat_id;
                    $params['sql']        = 'AND P.post_id IN (SELECT META.post_id FROM ' . dcCore::app()->prefix . 'meta META WHERE META.post_id = P.post_id ' . "AND META.meta_type = 'relatedEntries' ) ";
                    dcCore::app()->blog->withoutPassword(false);
                    dcCore::app()->admin->counter = dcCore::app()->blog->getPosts($params, true);
                } catch (Exception $e) {
                    dcCore::app()->error->add($e->getMessage());
                }
                $my_categories_combo[
                    str_repeat('&nbsp;', ($categories->level - 1) * 4) .
                    Html::escapeHTML($categories->cat_title) . ' (' . dcCore::app()->admin->counter->f(0) . ')'
                ] = $categories->cat_id;
            }

            $filters->append((new Filter('cat_id'))
                ->param()
                ->title(__('Category:'))
                ->options($my_categories_combo)
                ->prime(true));
        }
    }

    public static function adminPageHelpBlock(ArrayObject $blocks): void
    {
        if (array_search('core_post', $blocks->getArrayCopy(), true) !== false) {
            $blocks->append('post');
        }
    }

    public static function postHeaders(): string
    {
        

        if (!My::settings()->relatedEntries_enabled) {
            return '';
        }

        if (isset($_GET['p'])) {
            return '';
        }

        return
        '<script>' . "\n" .
        '$(document).ready(function() {' . "\n" .
            '$(\'#relatedEntries-area label\').toggleWithLegend($(\'#relatedEntries-list\'), {' . "\n" .
                'legend_click: true,' . "\n" .
                'user_pref: \'dcx_relatedEntries_detail\'' . "\n" .

            '});' . "\n" .
            '$(\'a.link-remove\').click(function() {' . "\n" .
            'msg = \'' . __('Are you sure you want to remove this link to a related post?') . '\';' . "\n" .
            'if (!window.confirm(msg)) {' . "\n" .
                'return false;' . "\n" .
            '}' . "\n" .
            '});' . "\n" .
            '$(\'a.links-remove\').click(function() {' . "\n" .
            'msg = \'' . __('Are you sure you want to remove all links to related posts?') . '\';' . "\n" .
            'if (!window.confirm(msg)) {' . "\n" .
                'return false;' . "\n" .
            '}' . "\n" .
            '});' . "\n" .
        '});' . "\n" .
        '</script>' .
        '<style type="text/css">' . "\n" .
        'a.links-remove {' . "\n" .
        'color : #c44d58;' . "\n" .
        '}' . "\n" .
        '.add {' . "\n" .
            'font-size : 1.5rem;' . "\n" .
            '}' . "\n" .
        '</style>';
    }

    public static function adminPostForm($post)
    {
        

        $postTypes = ['post'];

        if (!My::settings()->relatedEntries_enabled) {
            return;
        }
        if (is_null($post) || !in_array($post->post_type, $postTypes)) {
            return;
        }

        $id      = $post->post_id;
        $type    = $post->post_type;
        $meta    = dcCore::app()->meta;
        $meta_rs = $meta->getMetaStr($post->post_meta, 'relatedEntries');

        if (!$meta_rs) {
            echo
                '<div class="area" id="relatedEntries-area">' .
                '<label class="bold" for="relatedEntries-list">' . __('Related entries:') . '</label>' .
                '<span class="form-note">' . __('Links to related posts.') . '</span>' .
                '<div id="relatedEntries-list" >' .
                '<p>' . __('No related posts') . '</p>' .
                '<p class="add"><a href="' . dcCore::app()->adminurl->get('admin.plugin.' . My::id()) . '&amp;id=' . $id . '&amp;upd=1&amp;addlinks=1"><strong>' . __('Add links') . '</strong></a></p>' .
                '</div>' .
                '</div>';
        } else {
            echo
                '<div class="area" id="relatedEntries-area">' .
                '<label class="bold" for="relatedEntries-list">' . __('Related entries:') . '</label>' .
                '<span class="form-note">' . __('Links to related posts.') . '</span>' .
                '<div id="relatedEntries-list" >';

            // Get related posts
            try {
                dcCore::app()->blog->withoutPassword(false);

                $params['post_id']              = $meta->splitMetaValues($meta_rs);
                $params['no_content']           = true;
                $params['post_type']            = ['post'];
                $posts                          = dcCore::app()->blog->getPosts($params);
                $counter                        = dcCore::app()->blog->getPosts($params, true);
                dcCore::app()->admin->post_list = new BackendMiniList($posts, $counter->f(0));
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());
            }
            dcCore::app()->admin->page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            dcCore::app()->admin->nb_per_page = UserPref::getUserFilters('pages', 'nb');

            echo
                '<div id="form-entries">' .
                dcCore::app()->admin->post_list->display(dcCore::app()->admin->page, dcCore::app()->admin->nb_per_page) .
                '</div>';
            echo

            '<p class="two-boxes add"><a href="' . dcCore::app()->adminurl->get('admin.plugin.' . My::id()) . '&amp;id=' . $id . '&amp;addlinks=1"><strong>' . __('Add links') . '</strong></a></p>' .
            '<p class="two-boxes right"><a class="links-remove delete" href="' . dcCore::app()->adminurl->get('admin.plugin.' . My::id()) . '&amp;id=' . $id . '&amp;r_id=' . $meta_rs . '&upd=1">' . __('Remove all links') . '</a></p>' .

            form::hidden(['relatedEntries'], $meta_rs) .
            '</div>' .
            '</div>';
        }
    }
}
