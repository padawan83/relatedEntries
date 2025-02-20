<?php
/**
 * @brief Related Entries, a plugin for Dotclear 2
 *
 * @package    Dotclear
 * @subpackage Plugins
 *
 * @author Philippe aka amalgame
 *
 * @copyright GPL-2.0 [https://www.gnu.org/licenses/gpl-2.0.html]
 */
$this->registerModule(
    'Related entries',
    'Add links to other related posts',
    'Philippe aka amalgame',
    '4.4',
    [
        'requires'    => [['core', '2.33']],
        'permissions' => 'My',
        'priority'    => 3000,
        'type'        => 'plugin',
        'support'     => 'https://github.com/Philippe-dev/relatedEntries',
    ]
);
