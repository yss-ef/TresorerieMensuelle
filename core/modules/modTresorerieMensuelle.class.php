<?php
/* Copyright (C) 2004-2018  Laurent Destailleur        <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019  Nicolas ZABOURI             <info@inovea-conseil.com>
 * Copyright (C) 2019-2024  Frédéric France             <frederic.france@free.fr>
 * Copyright (C) 2025       SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \defgroup   tresoreriemensuelle     Module TresorerieMensuelle
 * \brief      TresorerieMensuelle module descriptor.
 *
 * \file       htdocs/tresoreriemensuelle/core/modules/modTresorerieMensuelle.class.php
 * \ingroup    tresoreriemensuelle
 * \brief      Description and activation file for module TresorerieMensuelle
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 * Description and activation class for module TresorerieMensuelle
 */
class modTresorerieMensuelle extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $conf, $langs;

        $this->db = $db;

        // Id for module (must be unique).
        $this->numero = 500000;

        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'tresoreriemensuelle';

        // Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
        $this->family = "other";

        // Module position in the family on 2 digits ('01', '10', '20', ...)
        $this->module_position = '90';

        // Module label (no space allowed), used if translation string 'ModuleTresorerieMensuelleName' not found (TresorerieMensuelle is name of module).
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Module description, used if translation string 'ModuleTresorerieMensuelleDesc' not found (TresorerieMensuelle is name of module).
        $this->description = "Tableau de bord qui affiche la tresorerie mensuel du mois en cours";
        $this->descriptionlong = "Tableau de bord qui affiche la tresorerie mensuel du mois en cours";

        // Author
        $this->editor_name = 'yss_ef';
        $this->editor_url = '';

        // Version
        $this->version = '1.0';

        // Key used in llx_const table to save module status enabled/disabled
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

        // Name of image file used for this module.
        $this->picto = 'fa-file';

        // Features supported by module
        $this->module_parts = array(
            'triggers' => 0,
            'login' => 0,
            'substitutions' => 0,
            'menus' => 0,
            'tpl' => 0,
            'barcode' => 0,
            'models' => 0,
            'printing' => 0,
            'theme' => 0,
            'css' => array(),
            'js' => array(),
            'hooks' => array(),
            'moduleforexternal' => 0,
            'websitetemplates' => 0,
            'captcha' => 0
        );

        // Data directories to create when module is enabled.
        $this->dirs = array("/tresoreriemensuelle/temp");

        // Config pages.
        $this->config_page_url = array("setup.php@tresoreriemensuelle");

        // Dependencies
        $this->hidden = getDolGlobalInt('MODULE_TRESORERIEMENSUELLE_DISABLED');
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();

        // Language file
        $this->langfiles = array("tresoreriemensuelle@tresoreriemensuelle");

        // Prerequisites
        $this->phpmin = array(7, 1);
        $this->need_dolibarr_version = array(19, -3);
        $this->need_javascript_ajax = 0;
        
        // Constants
        $this->const = array();

        // Main menu entries to add
        $this->menu = array();
        $r = 0;

        // Top Menu
        $this->menu[$r++] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'Tableau de Bord Trésorerie', // --- MODIFICATION : Titre plus clair
            'prefix' => img_picto('', 'fas fa-chart-line', 'class="pictofixedwidth valignmiddle"'), // --- MODIFICATION : Icône
            'mainmenu' => 'tresoreriemensuelle',
            'leftmenu' => '',
            'url' => '/tresoreriemensuelle/tresoreriemensuelleindex.php',
            'langs' => 'tresoreriemensuelle@tresoreriemensuelle',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("tresoreriemensuelle")',
            'perms' => '1',
            'target' => '',
            'user' => 2
        );
        
        // --- DÉBUT DU BLOC AJOUTÉ POUR LE MENU DE GAUCHE ---

        
        // --- FIN DU BLOC AJOUTÉ ---
    }

    /**
     * Function called when module is enabled.
     * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     * It also creates data directories
     *
     * @param      string  $options    Options when enabling module ('', 'noboxes')
     * @return     int<0,1>            1 if OK, <=0 if KO
     */
    public function init($options = '')
    {
        $sql = array();
        return $this->_init($sql, $options);
    }

    /**
     * Function called when module is disabled.
     * Remove from database constants, boxes and permissions from Dolibarr database.
     * Data directories are not deleted
     *
     * @param  string      $options    Options when enabling module ('', 'noboxes')
     * @return int<0,1>                1 if OK, <=0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}