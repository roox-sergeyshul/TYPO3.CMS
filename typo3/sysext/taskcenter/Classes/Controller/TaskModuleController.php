<?php
namespace TYPO3\CMS\Taskcenter\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\BaseScriptClass;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Taskcenter\TaskInterface;

/**
 * This class provides a taskcenter for BE users
 */
class TaskModuleController extends BaseScriptClass
{
    /**
     * @var array
     */
    protected $pageinfo;

    /**
     * ModuleTemplate Container
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'user_task';

    /**
     * Initializes the Module
     */
    public function __construct()
    {
        $this->moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->getLanguageService()->includeLLFile('EXT:taskcenter/Resources/Private/Language/locallang_task.xlf');
        $this->MCONF = [
            'name' => $this->moduleName
        ];
        parent::init();
    }

    /**
     * Adds items to the ->MOD_MENU array. Used for the function menu selector.
     *
     * @return void
     */
    public function menuConfig()
    {
        $this->MOD_MENU = ['mode' => []];
        $this->MOD_MENU['mode']['information'] = $this->getLanguageService()->sL('LLL:EXT:taskcenter/Resources/Private/Language/locallang.xlf:task_overview');
        $this->MOD_MENU['mode']['tasks'] = $this->getLanguageService()->sL('LLL:EXT:taskcenter/Resources/Private/Language/locallang.xlf:task_tasks');
        /* Copied from parent::menuConfig, because parent is hardcoded to menu.function,
         * however menu.function is already used for the individual tasks.
         * Therefore we use menu.mode here.
         */
        // Page/be_user TSconfig settings and blinding of menu-items
        $this->modTSconfig = BackendUtility::getModTSconfig($this->id, 'mod.' . $this->moduleName);
        $this->MOD_MENU['mode'] = $this->mergeExternalItems($this->MCONF['name'], 'mode', $this->MOD_MENU['mode']);
        $this->MOD_MENU['mode'] = BackendUtility::unsetMenuItems($this->modTSconfig['properties'], $this->MOD_MENU['mode'], 'menu.mode');
        parent::menuConfig();
    }

    /**
     * Generates the menu based on $this->MOD_MENU
     *
     * @throws \InvalidArgumentException
     */
    protected function generateMenu()
    {
        $menu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('WebFuncJumpMenu');
        foreach ($this->MOD_MENU['mode'] as $controller => $title) {
            $item = $menu
                ->makeMenuItem()
                ->setHref(
                    BackendUtility::getModuleUrl(
                        $this->moduleName,
                        [
                            'id' => $this->id,
                            'SET' => [
                                'mode' => $controller
                            ]
                        ]
                    )
                )
                ->setTitle($title);
            if ($controller === $this->MOD_SETTINGS['mode']) {
                $item->setActive(true);
            }
            $menu->addMenuItem($item);
        }
        $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    /**
     * Injects the request object for the current request or subrequest
     * Simply calls main() and writes the content to the response
     *
     * @param ServerRequestInterface $request the current request
     * @param ResponseInterface $response
     * @return ResponseInterface the response with the content
     */
    public function mainAction(ServerRequestInterface $request, ResponseInterface $response)
    {
        $GLOBALS['SOBE'] = $this;
        $this->main();

        $this->moduleTemplate->setContent($this->content);

        $response->getBody()->write($this->moduleTemplate->renderContent());
        return $response;
    }

    /**
     * Creates the module's content. In this case it rather acts as a kind of #
     * dispatcher redirecting requests to specific tasks.
     *
     * @return void
     */
    public function main()
    {
        $this->getButtons();
        $this->generateMenu();
        $this->moduleTemplate->addJavaScriptCode(
            'TaskCenterInlineJavascript',
            'if (top.fsMod) { top.fsMod.recentIds["web"] = 0; }'
        );

        // Render content depending on the mode
        $mode = (string)$this->MOD_SETTINGS['mode'];
        if ($mode === 'information') {
            $this->renderInformationContent();
        } else {
            $this->renderModuleContent();
        }
        // Renders the module page
        $this->moduleTemplate->setTitle($this->getLanguageService()->getLL('title'));
    }

    /**
     * Prints out the module's HTML
     *
     * @return void
     */
    public function printContent()
    {
        echo $this->content;
    }

    /**
     * Generates the module content by calling the selected task
     *
     * @return void
     */
    protected function renderModuleContent()
    {
        $chosenTask = (string)$this->MOD_SETTINGS['function'];
        // Render the taskcenter task as default
        if (empty($chosenTask) || $chosenTask == 'index') {
            $chosenTask = 'taskcenter.tasks';
        }
        // Render the task
        $actionContent = '';
        $flashMessage = null;
        list($extKey, $taskClass) = explode('.', $chosenTask, 2);
        if (class_exists($taskClass)) {
            $taskInstance = GeneralUtility::makeInstance($taskClass, $this);
            if ($taskInstance instanceof TaskInterface) {
                // Check if the task is restricted to admins only
                if ($this->checkAccess($extKey, $taskClass)) {
                    $actionContent .= $taskInstance->getTask();
                } else {
                    $flashMessage = GeneralUtility::makeInstance(
                        FlashMessage::class,
                        $this->getLanguageService()->getLL('error-access'),
                        $this->getLanguageService()->getLL('error_header'),
                        FlashMessage::ERROR
                    );
                }
            } else {
                // Error if the task is not an instance of \TYPO3\CMS\Taskcenter\TaskInterface
                $flashMessage = GeneralUtility::makeInstance(
                    FlashMessage::class,
                    sprintf($this->getLanguageService()->getLL('error_no-instance'), $taskClass, TaskInterface::class),
                    $this->getLanguageService()->getLL('error_header'),
                    FlashMessage::ERROR
                );
            }
        } else {
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->getLanguageService()->sL('LLL:EXT:taskcenter/Resources/Private/Language/locallang_mod.xlf:mlang_labels_tabdescr'),
                $this->getLanguageService()->sL('LLL:EXT:taskcenter/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab'),
                FlashMessage::INFO
            );
        }

        if ($flashMessage) {
            /** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
            $flashMessageService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessageService::class);
            /** @var $defaultFlashMessageQueue \TYPO3\CMS\Core\Messaging\FlashMessageQueue */
            $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);
        }

        $content = '<div class="taskcenter">
                        <div class="row">
                            <div class="col-sm-4 col-md-3">
                                <div class="taskcenter-menu">
                                    ' . $this->indexAction() . '
                                </div>
                            </div>
                            <div class="col-sm-8 col-md-9">
                                <div class="taskcenter-content taskcenter-content-' . strtolower(str_replace('\\', '-', htmlspecialchars(($extKey . '-' . $taskClass)))) . '">
                                    ' . $actionContent . '
                                </div>
                            </div>
                        </div>
                    </div>';
        $this->content .= $content;
    }

    /**
     * Generates the information content
     *
     * @return void
     */
    protected function renderInformationContent()
    {
        $content = $this->description($this->getLanguageService()->getLL('mlang_tabs_tab'), $this->getLanguageService()->sL('LLL:EXT:taskcenter/Resources/Private/Language/locallang_mod.xlf:mlang_labels_tabdescr'));
        $content .= $this->getLanguageService()->getLL('taskcenter-about');
        if ($this->getBackendUser()->isAdmin()) {
            $content .= '<br /><br />' . $this->description($this->getLanguageService()->getLL('taskcenter-adminheader'), $this->getLanguageService()->getLL('taskcenter-admin'));
        }
        $this->content .= $content;
    }

    /**
     * Render the headline of a task including a title and an optional description.
     *
     * @param string $title Title
     * @param string $description Description
     * @return string formatted title and description
     */
    public function description($title, $description = '')
    {
        $content = '<h1>' . nl2br(htmlspecialchars($title)) . '</h1>';
        if (!empty($description)) {
            $content .= '<p>' . nl2br(htmlspecialchars($description)) . '</p>';
        }
        return $content;
    }

    /**
     * Render a list of items as a nicely formated definition list including a
     * link, icon, title and description.
     * The keys of a single item are:
     * - title:             Title of the item
     * - link:              Link to the task
     * - icon:              Path to the icon or Icon as HTML if it begins with <img
     * - description:       Description of the task, using htmlspecialchars()
     * - descriptionHtml:   Description allowing HTML tags which will override the
     * description
     *
     * @param array $items List of items to be displayed in the definition list.
     * @param bool $mainMenu Set it to TRUE to render the main menu
     * @return string Fefinition list
     */
    public function renderListMenu($items, $mainMenu = false)
    {
        $content = ($section = '');
        $count = 0;
        // Change the sorting of items to the user's one
        if ($mainMenu) {
            $this->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Taskcenter/Taskcenter');
            $userSorting = unserialize($this->getBackendUser()->uc['taskcenter']['sorting']);
            if (is_array($userSorting)) {
                $newSorting = [];
                foreach ($userSorting as $item) {
                    if (isset($items[$item])) {
                        $newSorting[] = $items[$item];
                        unset($items[$item]);
                    }
                }
                $items = $newSorting + $items;
            }
        }
        if (is_array($items) && !empty($items)) {
            foreach ($items as $itemKey => $item) {
                $title = htmlspecialchars($item['title']);
                $icon = ($additionalClass = ($collapsedStyle = ''));
                // Check for custom icon
                if (!empty($item['icon'])) {
                    if (strpos($item['icon'], '<img ') === false) {
                        $iconFile = GeneralUtility::getFileAbsFileName($item['icon']);
                        if (@is_file($iconFile)) {
                            $icon = '<img src="' . htmlspecialchars(PathUtility::getAbsoluteWebPath($iconFile)) . '" width="16" height="16" title="' . $title . '" alt="' . $title . '" />';
                        }
                    } else {
                        $icon = $item['icon'];
                    }
                }
                $description = $item['descriptionHtml'] ?: '<p>' . nl2br(htmlspecialchars($item['description'])) . '</p>';
                $id = $this->getUniqueKey($item['uid']);
                $contentId = strtolower(str_replace('\\', '-', $id));
                // Collapsed & expanded menu items
                if (isset($this->getBackendUser()->uc['taskcenter']['states'][$id]) && $this->getBackendUser()->uc['taskcenter']['states'][$id]) {
                    $collapsed = true;
                    $collapseIcon = $this->moduleTemplate->getIconFactory()->getIcon('actions-view-list-expand', Icon::SIZE_SMALL)->render('inline');
                } else {
                    $collapsed = false;
                    $collapseIcon = $this->moduleTemplate->getIconFactory()->getIcon('actions-view-list-collapse', Icon::SIZE_SMALL)->render('inline');
                }
                // Active menu item
                $panelState = (string)$this->MOD_SETTINGS['function'] == $item['uid'] ? 'panel-active' : 'panel-default';
                $content .= '<li id="el_' . $id . '">
                                <div id="' . $contentId . '" data-taskcenter-id="' . $id . '" class="panel ' . $panelState . '">
                                    <div class="panel-heading">
                                        <div class="panel-heading-right">
                                            <a href="#task_content_' . $contentId . '" class="panel-header-collapse t3js-taskcenter-header-collapse" role="button" data-toggle="collapse" data-uid="' . $contentId . '" aria-expanded="' . ($collapsed ? 'false' : 'true') . '">
                                                ' . $collapseIcon . '
                                            </a>
                                        </div>
                                        <div class="panel-heading-left">
                                            <a href="' . $item['link'] . '" class="panel-title">
                                                ' . ($icon ? '<span class="panel-title-icon">' . $icon . '</span>' : '') . '
                                                <span class="panel-title-name">'
                                                    . $title . ' '
                                                    . $this->moduleTemplate->getIconFactory()->getIcon(
                                                        'actions-view-table-expand',
                                                        Icon::SIZE_SMALL
                                                    )->render('inline')
                                                . '</span>
                                            </a>
                                        </div>
                                    </div>
                                    <div id="task_content_' . $contentId . '" class="panel-collapse collapse t3js-taskcenter-collapse ' . ($collapsed ? '' : 'in') . '" aria-expanded="true">
                                        <div class="panel-body">
                                            ' . $description . '
                                        </div>
                                    </div>
                                </div>
							</li>';
                $count++;
            }
            $navigationId = $mainMenu ? 'id="task-list"' : '';
            $content = '<ul ' . $navigationId . ' class="list-unstyled">' . $content . '</ul>';
        }
        return $content;
    }

    /**
     * Shows an overview list of available reports.
     *
     * @return string List of available reports
     */
    protected function indexAction()
    {
        $content = '';
        $tasks = [];
        $defaultIcon = 'EXT:taskcenter/Resources/Public/Icons/module-taskcenter.svg';
        // Render the tasks only if there are any available
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['taskcenter']) && !empty($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['taskcenter'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['taskcenter'] as $extKey => $extensionReports) {
                foreach ($extensionReports as $taskClass => $task) {
                    if (!$this->checkAccess($extKey, $taskClass)) {
                        continue;
                    }
                    $link = BackendUtility::getModuleUrl('user_task') . '&SET[function]=' . $extKey . '.' . $taskClass;
                    $taskTitle = $this->getLanguageService()->sL($task['title']);
                    $taskDescriptionHtml = '';

                    if (class_exists($taskClass)) {
                        $taskInstance = GeneralUtility::makeInstance($taskClass, $this);
                        if ($taskInstance instanceof TaskInterface) {
                            $taskDescriptionHtml = $taskInstance->getOverview();
                        }
                    }
                    // Generate an array of all tasks
                    $uniqueKey = $this->getUniqueKey($extKey . '.' . $taskClass);
                    $tasks[$uniqueKey] = [
                        'title' => $taskTitle,
                        'descriptionHtml' => $taskDescriptionHtml,
                        'description' => $this->getLanguageService()->sL($task['description']),
                        'icon' => !empty($task['icon']) ? $task['icon'] : $defaultIcon,
                        'link' => $link,
                        'uid' => $extKey . '.' . $taskClass
                    ];
                }
            }
            $content .= $this->renderListMenu($tasks, true);
        } else {
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->getLanguageService()->getLL('no-tasks'),
                '',
                FlashMessage::INFO
            );
            /** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
            $flashMessageService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessageService::class);
            /** @var $defaultFlashMessageQueue \TYPO3\CMS\Core\Messaging\FlashMessageQueue */
            $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);
        }
        return $content;
    }

    /**
     * Create the panel of buttons for submitting the form or otherwise
     * perform operations.
     *
     * @return void
     */
    protected function getButtons()
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        // Shortcut
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setModuleName($this->moduleName)
            ->setSetVariables(['function']);
        $buttonBar->addButton($shortcutButton);
    }

    /**
     * Check the access to a task. Considered are:
     * - Admins are always allowed
     * - Tasks can be restriced to admins only
     * - Tasks can be blinded for Users with TsConfig taskcenter.<extensionkey>.<taskName> = 0
     *
     * @param string $extKey Extension key
     * @param string $taskClass Name of the task
     * @return bool Access to the task allowed or not
     */
    protected function checkAccess($extKey, $taskClass)
    {
        // Check if task is blinded with TsConfig (taskcenter.<extkey>.<taskName>
        $tsConfig = $this->getBackendUser()->getTSConfig('taskcenter.' . $extKey . '.' . $taskClass);
        if (isset($tsConfig['value']) && (int)$tsConfig['value'] === 0) {
            return false;
        }
        // Admins are always allowed
        if ($this->getBackendUser()->isAdmin()) {
            return true;
        }
        // Check if task is restricted to admins
        if ((int)$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['taskcenter'][$extKey][$taskClass]['admin'] === 1) {
            return false;
        }
        return true;
    }

    /**
     * Returns HTML code to dislay an url in an iframe at the right side of the taskcenter
     *
     * @param string $url Url to display
     * @return string Code that inserts the iframe (HTML)
     */
    public function urlInIframe($url)
    {
        return '<div class="panel panel-default"><iframe scrolling="auto"  width="100%" src="' . $url . '" name="list_frame" id="list_frame" frameborder="no" style="height: 500px"></iframe></div>';
    }

    /**
     * Create a unique key from a string which can be used in JS for sorting
     * Therefore '_' are replaced
     *
     * @param string $string string which is used to generate the identifier
     * @return string Modified string
     */
    protected function getUniqueKey($string)
    {
        $search = ['.', '_'];
        $replace = ['-', ''];
        return str_replace($search, $replace, $string);
    }

    /**
     * Returns the current BE user.
     *
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Returns LanguageService
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @return ModuleTemplate
     */
    public function getModuleTemplate()
    {
        return $this->moduleTemplate;
    }
}
