<?php

require_once dirname(__FILE__).'/sfTaskExtraTestBaseTask.class.php';

/**
 * Launches a plugin test suite.
 * 
 * @package     sfTaskExtraPlugin
 * @subpackage  task
 * @author      Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @version     SVN: $Id$
 */
class sfTestPluginTask extends sfTaskExtraTestBaseTask
{
  protected
    $pluginPaths = array();

  /**
   * @see sfTask
   */
  protected function configure()
  {
    $this->addArguments(array(
      new sfCommandArgument('plugin', sfCommandArgument::REQUIRED | sfCommandArgument::IS_ARRAY, 'The plugin name'),
    ));

    $this->addOptions(array(
      new sfCommandOption('only', null, sfCommandOption::PARAMETER_REQUIRED, 'Only run "unit" or "functional" tests'),
    ));

    $this->namespace = 'test';
    $this->name = 'plugin';

    $this->briefDescription = 'Launches a plugin test suite';

    $this->detailedDescription = <<<EOF
The [test:plugin|INFO] task launches a plugin's test suite:

  [./symfony test:plugin sfExamplePlugin|INFO]

You can specify only unit or functional tests with the [--only|COMMENT] option:

  [./symfony test:plugin sfExamplePlugin --only=unit|INFO]
EOF;
  }

  /**
   * @see sfTask
   */
  protected function execute($arguments = array(), $options = array())
  {
    $this->pluginPaths = array();
    foreach ($arguments['plugin'] as $plugin)
    {
      $this->checkPluginExists($plugin);
      $this->pluginPaths[] = $this->configuration->getPluginConfiguration($plugin)->getRootDir();
    }

    if ($options['only'] && !in_array($options['only'], array('unit', 'functional')))
    {
      throw new sfCommandException(sprintf('The --only option must be either "unit" or "functional" ("%s" given)', $options['only']));
    }

    // use the test:* task but filter the files
    $this->dispatcher->connect('task.test.filter_test_files', array($this, 'filterTestFiles'));

    switch ($options['only'])
    {
      case 'unit':
        $task = new sfTestUnitTask($this->dispatcher, $this->formatter);
        break;
      case 'functional':
        $task = new sfTestFunctionalTask($this->dispatcher, $this->formatter);
        break;
      default:
        $task = new sfTestAllTask($this->dispatcher, $this->formatter);
    }

    $task->setConfiguration($this->configuration);
    $task->setCommandApplication($this->commandApplication);
    $task->run();

    $this->dispatcher->disconnect('task.test.filter_test_files', array($this, 'filterTestFiles'));
  }

  /**
   * Listens to the task.test.filter_test_files event.
   * 
   * @param sfEvent $event
   * @param array   $files
   * 
   * @return array
   */
  public function filterTestFiles(sfEvent $event, $files)
  {
    return array_filter($files, array($this, 'filterTestFilesCallback'));
  }

  protected function filterTestFilesCallback($file)
  {
    foreach ($this->pluginPaths as $pluginPath)
    {
      if (0 === strpos($file, $pluginPath))
      {
        return true;
      }
    }
  }
}
