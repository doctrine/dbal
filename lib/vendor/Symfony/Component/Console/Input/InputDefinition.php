<?php

namespace Symfony\Component\Console\Input;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * A InputDefinition represents a set of valid command line arguments and options.
 *
 * Usage:
 *
 *     $definition = new InputDefinition(array(
 *       new InputArgument('name', InputArgument::REQUIRED),
 *       new InputOption('foo', 'f', InputOption::VALUE_REQUIRED),
 *     ));
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class InputDefinition
{
    protected $arguments;
    protected $requiredCount;
    protected $hasAnArrayArgument = false;
    protected $hasOptional;
    protected $options;
    protected $shortcuts;

    /**
     * Constructor.
     *
     * @param array $definition An array of InputArgument and InputOption instance
     */
    public function __construct(array $definition = array())
    {
        $this->setDefinition($definition);
    }

    public function setDefinition(array $definition)
    {
        $arguments = array();
        $options = array();
        foreach ($definition as $item) {
            if ($item instanceof InputOption) {
                $options[] = $item;
            } else {
                $arguments[] = $item;
            }
        }

        $this->setArguments($arguments);
        $this->setOptions($options);
    }

    /**
     * Sets the InputArgument objects.
     *
     * @param array $arguments An array of InputArgument objects
     */
    public function setArguments($arguments = array())
    {
        $this->arguments          = array();
        $this->requiredCount      = 0;
        $this->hasOptional        = false;
        $this->hasAnArrayArgument = false;
        $this->addArguments($arguments);
    }

    /**
     * Add an array of InputArgument objects.
     *
     * @param InputArgument[] $arguments An array of InputArgument objects
     */
    public function addArguments($arguments = array())
    {
        if (null !== $arguments) {
            foreach ($arguments as $argument) {
                $this->addArgument($argument);
            }
        }
    }

    /**
     * Add an InputArgument object.
     *
     * @param InputArgument $argument An InputArgument object
     *
     * @throws \LogicException When incorrect argument is given
     */
    public function addArgument(InputArgument $argument)
    {
        if (isset($this->arguments[$argument->getName()])) {
            throw new \LogicException(sprintf('An argument with name "%s" already exist.', $argument->getName()));
        }

        if ($this->hasAnArrayArgument) {
            throw new \LogicException('Cannot add an argument after an array argument.');
        }

        if ($argument->isRequired() && $this->hasOptional) {
            throw new \LogicException('Cannot add a required argument after an optional one.');
        }

        if ($argument->isArray()) {
            $this->hasAnArrayArgument = true;
        }

        if ($argument->isRequired()) {
            ++$this->requiredCount;
        } else {
            $this->hasOptional = true;
        }

        $this->arguments[$argument->getName()] = $argument;
    }

    /**
     * Returns an InputArgument by name or by position.
     *
     * @param string|integer $name The InputArgument name or position
     *
     * @return InputArgument An InputArgument object
     *
     * @throws \InvalidArgumentException When argument given doesn't exist
     */
    public function getArgument($name)
    {
        $arguments = is_int($name) ? array_values($this->arguments) : $this->arguments;

        if (!$this->hasArgument($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
        }

        return $arguments[$name];
    }

    /**
     * Returns true if an InputArgument object exists by name or position.
     *
     * @param string|integer $name The InputArgument name or position
     *
     * @return Boolean true if the InputArgument object exists, false otherwise
     */
    public function hasArgument($name)
    {
        $arguments = is_int($name) ? array_values($this->arguments) : $this->arguments;

        return isset($arguments[$name]);
    }

    /**
     * Gets the array of InputArgument objects.
     *
     * @return array An array of InputArgument objects
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Returns the number of InputArguments.
     *
     * @return integer The number of InputArguments
     */
    public function getArgumentCount()
    {
        return $this->hasAnArrayArgument ? PHP_INT_MAX : count($this->arguments);
    }

    /**
     * Returns the number of required InputArguments.
     *
     * @return integer The number of required InputArguments
     */
    public function getArgumentRequiredCount()
    {
        return $this->requiredCount;
    }

    /**
     * Gets the default values.
     *
     * @return array An array of default values
     */
    public function getArgumentDefaults()
    {
        $values = array();
        foreach ($this->arguments as $argument) {
            $values[$argument->getName()] = $argument->getDefault();
        }

        return $values;
    }

    /**
     * Sets the InputOption objects.
     *
     * @param array $options An array of InputOption objects
     */
    public function setOptions($options = array())
    {
        $this->options = array();
        $this->shortcuts = array();
        $this->addOptions($options);
    }

    /**
     * Add an array of InputOption objects.
     *
     * @param InputOption[] $options An array of InputOption objects
     */
    public function addOptions($options = array())
    {
        foreach ($options as $option) {
            $this->addOption($option);
        }
    }

    /**
     * Add an InputOption object.
     *
     * @param InputOption $option An InputOption object
     *
     * @throws \LogicException When option given already exist
     */
    public function addOption(InputOption $option)
    {
        if (isset($this->options[$option->getName()])) {
            throw new \LogicException(sprintf('An option named "%s" already exist.', $option->getName()));
        } else if (isset($this->shortcuts[$option->getShortcut()])) {
            throw new \LogicException(sprintf('An option with shortcut "%s" already exist.', $option->getShortcut()));
        }

        $this->options[$option->getName()] = $option;
        if ($option->getShortcut()) {
            $this->shortcuts[$option->getShortcut()] = $option->getName();
        }
    }

    /**
     * Returns an InputOption by name.
     *
     * @param string $name The InputOption name
     *
     * @return InputOption A InputOption object
     */
    public function getOption($name)
    {
        if (!$this->hasOption($name)) {
            throw new \InvalidArgumentException(sprintf('The "--%s" option does not exist.', $name));
        }

        return $this->options[$name];
    }

    /**
     * Returns true if an InputOption object exists by name.
     *
     * @param string $name The InputOption name
     *
     * @return Boolean true if the InputOption object exists, false otherwise
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    /**
     * Gets the array of InputOption objects.
     *
     * @return array An array of InputOption objects
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Returns true if an InputOption object exists by shortcut.
     *
     * @param string $name The InputOption shortcut
     *
     * @return Boolean true if the InputOption object exists, false otherwise
     */
    public function hasShortcut($name)
    {
        return isset($this->shortcuts[$name]);
    }

    /**
     * Gets an InputOption by shortcut.
     *
     * @return InputOption An InputOption object
     */
    public function getOptionForShortcut($shortcut)
    {
        return $this->getOption($this->shortcutToName($shortcut));
    }

    /**
     * Gets an array of default values.
     *
     * @return array An array of all default values
     */
    public function getOptionDefaults()
    {
        $values = array();
        foreach ($this->options as $option) {
            $values[$option->getName()] = $option->getDefault();
        }

        return $values;
    }

    /**
     * Returns the InputOption name given a shortcut.
     *
     * @param string $shortcut The shortcut
     *
     * @return string The InputOption name
     *
     * @throws \InvalidArgumentException When option given does not exist
     */
    protected function shortcutToName($shortcut)
    {
        if (!isset($this->shortcuts[$shortcut])) {
            throw new \InvalidArgumentException(sprintf('The "-%s" option does not exist.', $shortcut));
        }

        return $this->shortcuts[$shortcut];
    }

    /**
     * Gets the synopsis.
     *
     * @return string The synopsis
     */
    public function getSynopsis()
    {
        $elements = array();
        foreach ($this->getOptions() as $option) {
            $shortcut = $option->getShortcut() ? sprintf('-%s|', $option->getShortcut()) : '';
            $elements[] = sprintf('['.($option->isValueRequired() ? '%s--%s="..."' : ($option->isValueOptional() ? '%s--%s[="..."]' : '%s--%s')).']', $shortcut, $option->getName());
        }

        foreach ($this->getArguments() as $argument) {
            $elements[] = sprintf($argument->isRequired() ? '%s' : '[%s]', $argument->getName().($argument->isArray() ? '1' : ''));

            if ($argument->isArray()) {
                $elements[] = sprintf('... [%sN]', $argument->getName());
            }
        }

        return implode(' ', $elements);
    }

    /**
     * Returns a textual representation of the InputDefinition.
     *
     * @return string A string representing the InputDefinition
     */
    public function asText()
    {
        // find the largest option or argument name
        $max = 0;
        foreach ($this->getOptions() as $option) {
            $max = strlen($option->getName()) + 2 > $max ? strlen($option->getName()) + 2 : $max;
        }
        foreach ($this->getArguments() as $argument) {
            $max = strlen($argument->getName()) > $max ? strlen($argument->getName()) : $max;
        }
        ++$max;

        $text = array();

        if ($this->getArguments()) {
            $text[] = '<comment>Arguments:</comment>';
            foreach ($this->getArguments() as $argument) {
                if (null !== $argument->getDefault() && (!is_array($argument->getDefault()) || count($argument->getDefault()))) {
                    $default = sprintf('<comment> (default: %s)</comment>', is_array($argument->getDefault()) ? str_replace("\n", '', var_export($argument->getDefault(), true)): $argument->getDefault());
                } else {
                    $default = '';
                }

                $text[] = sprintf(" <info>%-${max}s</info> %s%s", $argument->getName(), $argument->getDescription(), $default);
            }

            $text[] = '';
        }

        if ($this->getOptions()) {
            $text[] = '<comment>Options:</comment>';

            foreach ($this->getOptions() as $option) {
                if ($option->acceptValue() && null !== $option->getDefault() && (!is_array($option->getDefault()) || count($option->getDefault()))) {
                    $default = sprintf('<comment> (default: %s)</comment>', is_array($option->getDefault()) ? str_replace("\n", '', print_r($option->getDefault(), true)): $option->getDefault());
                } else {
                    $default = '';
                }

                $multiple = $option->isArray() ? '<comment> (multiple values allowed)</comment>' : '';
                $text[] = sprintf(' %-'.$max.'s %s%s%s%s', '<info>--'.$option->getName().'</info>', $option->getShortcut() ? sprintf('(-%s) ', $option->getShortcut()) : '', $option->getDescription(), $default, $multiple);
            }

            $text[] = '';
        }

        return implode("\n", $text);
    }

    /**
     * Returns an XML representation of the InputDefinition.
     *
     * @param Boolean $asDom Whether to return a DOM or an XML string
     *
     * @return string|DOMDocument An XML string representing the InputDefinition
     */
    public function asXml($asDom = false)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->appendChild($definitionXML = $dom->createElement('definition'));

        $definitionXML->appendChild($argumentsXML = $dom->createElement('arguments'));
        foreach ($this->getArguments() as $argument) {
            $argumentsXML->appendChild($argumentXML = $dom->createElement('argument'));
            $argumentXML->setAttribute('name', $argument->getName());
            $argumentXML->setAttribute('is_required', $argument->isRequired() ? 1 : 0);
            $argumentXML->setAttribute('is_array', $argument->isArray() ? 1 : 0);
            $argumentXML->appendChild($descriptionXML = $dom->createElement('description'));
            $descriptionXML->appendChild($dom->createTextNode($argument->getDescription()));

            $argumentXML->appendChild($defaultsXML = $dom->createElement('defaults'));
            $defaults = is_array($argument->getDefault()) ? $argument->getDefault() : ($argument->getDefault() ? array($argument->getDefault()) : array());
            foreach ($defaults as $default) {
                $defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
                $defaultXML->appendChild($dom->createTextNode($default));
            }
        }

        $definitionXML->appendChild($optionsXML = $dom->createElement('options'));
        foreach ($this->getOptions() as $option) {
            $optionsXML->appendChild($optionXML = $dom->createElement('option'));
            $optionXML->setAttribute('name', '--'.$option->getName());
            $optionXML->setAttribute('shortcut', $option->getShortcut() ? '-'.$option->getShortcut() : '');
            $optionXML->setAttribute('accept_value', $option->acceptValue() ? 1 : 0);
            $optionXML->setAttribute('is_value_required', $option->isValueRequired() ? 1 : 0);
            $optionXML->setAttribute('is_multiple', $option->isArray() ? 1 : 0);
            $optionXML->appendChild($descriptionXML = $dom->createElement('description'));
            $descriptionXML->appendChild($dom->createTextNode($option->getDescription()));

            if ($option->acceptValue()) {
                $optionXML->appendChild($defaultsXML = $dom->createElement('defaults'));
                $defaults = is_array($option->getDefault()) ? $option->getDefault() : ($option->getDefault() ? array($option->getDefault()) : array());
                foreach ($defaults as $default) {
                    $defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
                    $defaultXML->appendChild($dom->createTextNode($default));
                }
            }
        }

        return $asDom ? $dom : $dom->saveXml();
    }
}
