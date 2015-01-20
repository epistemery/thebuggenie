<?php

    namespace thebuggenie\core\entities;

    use thebuggenie\core\entities\common\IdentifiableScoped;
    use thebuggenie\core\framework;

    /**
     * Module class, extended by all thebuggenie modules
     *
     * @author Daniel Andre Eikeland <zegenie@zegeniestudios.net>
     * @version 3.1
     * @license http://opensource.org/licenses/MPL-2.0 Mozilla Public License 2.0 (MPL 2.0)
     * @package thebuggenie
     * @subpackage core
     */

    /**
     * Module class, extended by all thebuggenie modules
     *
     * @package thebuggenie
     * @subpackage core
     *
     * @Table(name="\thebuggenie\core\entities\tables\Modules")
     */
    abstract class Module extends IdentifiableScoped
    {

        /**
         * The name of the object
         *
         * @var string
         * @Column(type="string", length=200)
         */
        protected $_name;

        /**
         * @var string
         * @Column(type="string", length=100)
         */
        protected $_classname = '';

        /**
         * @var boolean
         * @Column(type="boolean")
         */
        protected $_enabled = false;

        /**
         * @var string
         * @Column(type="string", length=10)
         */
        protected $_version = '';

        protected $_longname = '';
        protected $_shortname = '';
        protected $_showinconfig = false;
        protected $_module_config_title = '';
        protected $_module_config_description = '';
        protected $_description = '';
        protected $_availablepermissions = array();
        protected $_settings = array();
        protected $_routes = array();

        protected $_has_account_settings = false;
        protected $_account_settings_name = null;
        protected $_account_settings_logo = null;
        protected $_has_config_settings = false;

        protected static $_permissions = array();

        const MODULE_NORMAL = 1;
        const MODULE_AUTH = 2;
        const MODULE_GRAPH = 3;

        /**
         * Installs a module
         *
         * @param string $module_name the module key
         * @return boolean Whether the install succeeded or not
         */
        public static function installModule($module_name, $scope = null)
        {
            $scope_id = ($scope) ? $scope->getID() : framework\Context::getScope()->getID();
            if (!framework\Context::getScope() instanceof \thebuggenie\core\entities\Scope) throw new \Exception('No scope??');

            framework\Logging::log('installing module ' . $module_name);
            $module = tables\Modules::getTable()->installModule($module_name, $scope_id);
            $module->install($scope_id);
            framework\Logging::log('done (installing module ' . $module_name . ')');

            return $module;
        }

        /**
         * Upload a new module from ZIP archive
         *
         * @param file $module_archive the module archive file (.zip)
         * @return string the module name uploaded
         */
        public static function uploadModule($module_archive, $scope = null)
        {
            $zip = new ZipArchive();
            if ($zip->open($module_archive['tmp_name']) === false) {
                throw new \Exception('Can not open module archive ' . $module_archive['name']);
            }
            else
            {
                $module_name = preg_replace('/(\w*)\.zip$/i', '$1', $module_archive['name']);
                $module_info = $zip->getFromName('module');
                $module_details = explode('|',$zip->getFromName('class'));
                list($module_classname, $module_version) = $module_details;
                $module_basepath = THEBUGGENIE_MODULES_PATH . $module_name;

                if (($module_info & $module_details) === false)
                {
                    throw new \Exception('Invalid module archive ' . $module_archive['name']);
                }

                $modules = framework\Context::getModules();
                foreach($modules as $module)
                {
                    if ($module->getName() == $module_name || $module->getClassname() == $module_classname)
                    {
                        throw new \Exception('Conflict with the module ' . $module->getLongName() . ' that is already installed with version ' . $module->getVersion());
                    }
                }

                if (is_dir($module_basepath) === false)
                {
                    if (mkdir($module_basepath) === false)
                    {
                        framework\Logging::log('Try to upload module archive ' . $module_archive['name'] . ': unable to create module directory ' . $module_basepath);
                        throw new \Exception('Unable to create module directory ' . $module_basepath);
                    }
                    if ($zip->extractTo($module_basepath) === false)
                    {
                        framework\Logging::log('Try to upload module archive ' . $module_archive['name'] . ': unable to extract archive into ' . $module_basepath);
                        throw new \Exception('Unable to extract module into ' . $module_basepath);
                    }
                }
                return $module_name;
            }
            return null;
        }

        protected function _addAvailablePermissions() { }

        protected function _addListeners() { }

        abstract protected function _initialize();

        protected function _install($scope) { }

        protected function _uninstall() { }

        protected function _upgrade() { }

        /**
         * Class constructor
         */
        final public function _construct(\b2db\Row $row, $foreign_key = null)
        {
            if ($this->_version != $row->get(tables\Modules::VERSION))
            {
                throw new \Exception('This module must be upgraded to the latest version');
            }
        }

        protected function _loadFixtures($scope) { }

        final public function install($scope)
        {
            try
            {
                framework\Context::clearRoutingCache();
                framework\Context::clearPermissionsCache();
                $this->_install($scope);
                $b2db_classpath = THEBUGGENIE_MODULES_PATH . $this->_name . DS . 'entities' . DS . 'b2db';

                if (framework\Context::getScope()->isDefault() && is_dir($b2db_classpath))
                {
                    $b2db_classpath_handle = opendir($b2db_classpath);
                    while ($table_class_file = readdir($b2db_classpath_handle))
                    {
                        if (($tablename = mb_substr($table_class_file, 0, mb_strpos($table_class_file, '.'))) != '')
                        {
                            \b2db\Core::getTable("\\thebuggenie\\modules\\".$this->_name."\\entities\\b2db\\".$tablename)->create();
                        }
                    }
                }
                $this->_loadFixtures($scope);
            }
            catch (\Exception $e)
            {
                throw $e;
            }
        }

        public function log($message, $level = 1)
        {
            framework\Logging::log($message, $this->getName(), $level);
        }

        public static function disableModule($module_id)
        {
            tables\Modules::getTable()->disableModuleByID($module_id);
        }

        public static function removeModule($module_id)
        {
            tables\Modules::getTable()->removeModuleByID($module_id);
        }

        public final function isCore()
        {
            return in_array($this->_name, array('publish'));
        }

        public function disable()
        {
            self::disableModule($this->getID());
            $this->_enabled = false;
        }

        public function enable()
        {
            $crit = new \b2db\Criteria();
            $crit->addUpdate(tables\Modules::ENABLED, 1);
            tables\Modules::getTable()->doUpdateById($crit, $this->getID());
            $this->_enabled = true;
        }

        final public function upgrade()
        {
            framework\Context::clearRoutingCache();
            framework\Context::clearPermissionsCache();
            $this->_upgrade();
            $this->_version = static::VERSION;
            $this->save();
        }

        final public function uninstall($scope = null)
        {
            if ($this->isCore())
            {
                throw new \Exception('Cannot uninstall core modules');
            }
            $scope = ($scope === null) ? framework\Context::getScope()->getID() : $scope;
            $this->_uninstall($scope);
            tables\Modules::getTable()->doDeleteById($this->getID());
            framework\Settings::deleteModuleSettings($this->getName(), $scope);
            framework\Context::deleteModulePermissions($this->getName(), $scope);
            framework\Context::clearRoutingCache();
            framework\Context::clearPermissionsCache();
        }

        public function getClassname()
        {
            return $this->_classname;
        }

        public function __toString()
        {
            return $this->_name;
        }

        public function __call($func, $args)
        {
            throw new \Exception('Trying to call function ' . $func . '() in module ' . $this->_shortname . ', but the function does not exist');
        }

        public function setLongName($name)
        {
            $this->_longname = $name;
        }

        public function getLongName()
        {
            return $this->_longname;
        }

        public function addAvailablePermission($permission_name, $description, $target = 0)
        {
            $this->_availablepermissions[$permission_name] = array('description' => framework\Context::getI18n()->__($description), 'target_id' => $target);
        }

        public function getAvailablePermissions()
        {
            return $this->_availablepermissions;
        }

        public function getAvailableCommandLineCommands()
        {
            return array();
        }

        public function setPermission($uid, $gid, $tid, $allowed, $scope = null)
        {
            $scope = ($scope === null) ? framework\Context::getScope()->getID() : $scope;
            tables\ModulePermissions::getTable()->deleteByModuleAndUIDandGIDandTIDandScope($this->getName(), $uid, $gid, $tid, $scope);
            tables\ModulePermissions::getTable()->setPermissionByModuleAndUIDandGIDandTIDandScope($this->getName(), $uid, $gid, $tid, $allowed, $scope);
            if ($scope == framework\Context::getScope()->getID())
            {
                self::cacheAccessPermission($this->getName(), $uid, $gid, $tid, 0, $allowed);
            }
        }

        public function setConfigTitle($title)
        {
            $this->_module_config_title = $title;
        }

        public function getConfigTitle()
        {
            return $this->_module_config_title;
        }

        public function setConfigDescription($description)
        {
            $this->_module_config_description = $description;
        }

        public function getConfigDescription()
        {
            return $this->_module_config_description;
        }

        public function getVersion()
        {
            return $this->_version;
        }

        public function getType()
        {
            return self::MODULE_NORMAL;
        }

        /**
         * Shortcut for the global settings function
         *
         * @param string  $setting the name of the setting
         * @param integer $uid     the uid for the user to check
         *
         * @return mixed
         */
        public function getSetting($setting, $uid = 0)
        {
            return framework\Settings::get($setting, $this->getName(), framework\Context::getScope()->getID(), $uid);
        }

        public function saveSetting($setting, $value, $uid = 0, $scope = null)
        {
            $scope = ($scope === null) ? framework\Context::getScope()->getID() : $scope;
            return framework\Settings::saveSetting($setting, $value, $this->getName(), $scope, $uid);
        }

        public function deleteSetting($setting, $uid = null, $scope = null)
        {
            return framework\Settings::deleteSetting($setting, $this->getName(), $scope, $uid);
        }

        /**
         * Returns whether the module is enabled
         *
         * @return boolean
         */
        public function isEnabled()
        {
            /* Outdated modules can not be used */
            if ($this->isOutdated())
            {
                return false;
            }
            return $this->_enabled;
        }

        /**
         * Returns whether the module is out of date
         *
         * @return boolean
         */
        public function isOutdated()
        {
            if ($this->_version != static::VERSION)
            {
                return true;
            }
            return false;
        }

        public function addRoute($key, $url, $function, $params = array(), $csrf_enabled = false, $module_name = null)
        {
            $module_name = ($module_name !== null) ? $module_name : $this->getName();
            $this->_routes[] = array($key, $url, $module_name, $function, $params, $csrf_enabled);
        }

        final public function initialize()
        {
            $this->_initialize();
            if ($this->isEnabled())
            {
                $this->_addAvailablePermissions();
                $this->_addListeners();
            }
        }

        public function setDescription($description)
        {
            $this->_description = $description;
        }

        public function getDescription()
        {
            return $this->_description;
        }

        public static function getAllModulePermissions($module, $uid, $tid, $gid)
        {

            $crit = new \b2db\Criteria();
            $crit->addWhere(tables\ModulePermissions::MODULE_NAME, $module);
            //$sql = "select b2mp.allowed from tbg_2_modulepermissions b2mp where b2mp.module_name = '$module'";
            switch (true)
            {
                case ($uid != 0):
                    //$sql .= " and uid = $uid";
                    $crit->addWhere(tables\ModulePermissions::UID, $uid);
                case ($tid != 0):
                    //$sql .= " and tid = $tid";
                    $crit->addWhere(tables\ModulePermissions::TID, $tid);
                case ($gid != 0):
                    //$sql .= " and gid = $gid";
                    $crit->addWhere(tables\ModulePermissions::GID, $gid);
            }
            if (($uid + $tid + $gid) == 0)
            {
                //$sql .= " and uid = $uid and tid = $tid and gid = $gid";
                $crit->addWhere(tables\ModulePermissions::UID, $uid);
                $crit->addWhere(tables\ModulePermissions::TID, $tid);
                $crit->addWhere(tables\ModulePermissions::GID, $gid);
            }

            //$sql .= " AND b2mp.scope = " . framework\Context::getScope()->getID();
            $crit->addWhere(tables\ModulePermissions::SCOPE, framework\Context::getScope()->getID());

            //$res = b2db_sql_query($sql, \b2db\Core::getDBlink());

            #print $sql;

            $permissions = array();
            $res = tables\ModulePermissions::getTable()->doSelect($crit);

            while ($row = $res->getNextRow())
            {
                $permissions[] = array('allowed' => $row->get(tables\ModulePermissions::ALLOWED));
            }

            return $permissions;
        }

        public function loadHelpTitle($topic)
        {
            return $topic;
        }

        public function getRoute()
        {
            return 'login';
        }

        public function setHasAccountSettings($val = true)
        {
            $this->_has_account_settings = (bool) $val;
        }

        public function hasAccountSettings()
        {
            return $this->_has_account_settings;
        }

        public function setAccountSettingsName($name)
        {
            $this->_account_settings_name = $name;
        }

        public function getAccountSettingsName()
        {
            return framework\Context::geti18n()->__($this->_account_settings_name);
        }

        public function setAccountSettingsLogo($logo)
        {
            $this->_account_settings_logo = $logo;
        }

        public function getAccountSettingsLogo()
        {
            return $this->_account_settings_logo;
        }

        public function setHasConfigSettings($val = true)
        {
            $this->_has_config_settings = (bool) $val;
        }

        public function hasConfigSettings()
        {
            /* If the module is outdated, we may not access its settings */
            if ($this->isOutdated()): return false; endif;

            return $this->_has_config_settings;
        }

        public function hasProjectAwareRoute()
        {
            return false;
        }

        public function getTabKey()
        {
            return $this->getName();
        }

        public function postConfigSettings(framework\Request $request)
        {

        }

        public function postAccountSettings(framework\Request $request)
        {

        }

        /**
         * Return the items name
         *
         * @return string
         */
        public function getName()
        {
            return $this->_name;
        }

        /**
         * Set the edition name
         *
         * @param string $name
         */
        public function setName($name)
        {
            $this->_name = $name;
        }

    }