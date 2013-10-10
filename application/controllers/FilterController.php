<?php
// {{{ICINGA_LICENSE_HEADER}}}
/**
 * This file is part of Icinga 2 Web.
 *
 * Icinga 2 Web - Head for multiple monitoring backends.
 * Copyright (C) 2013 Icinga Development Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @copyright 2013 Icinga Development Team <info@icinga.org>
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author    Icinga Development Team <info@icinga.org>
 */
// {{{ICINGA_LICENSE_HEADER}}}

use Icinga\Web\Form;
use Icinga\Web\Controller\ActionController;
use Icinga\Filter\Filter;
use Icinga\Filter\FilterAttribute;
use Icinga\Filter\Type\TextFilter;
use Icinga\Application\Logger;
use Icinga\Module\Monitoring\Filter\Type\StatusFilter;
use Icinga\Module\Monitoring\Filter\UrlViewFilter;
use Icinga\Web\Url;

class FilterController extends ActionController
{
    /**
     * @var Filter
     */
    private $registry;

    public function indexAction()
    {
        $this->registry = new Filter();
        $filter = new UrlViewFilter();

        $this->view->form = new Form();
        $this->view->form->addElement(
            'text',
            'query',
            array(
                'name' => 'query',
                'label' => 'search',
                'type' => 'search',
                'data-icinga-component' => 'app/semanticsearch',
                'data-icinga-target'    => 'host',
                'helptext' => 'Filter test'
            )
        );
        $this->view->form->addElement(
            'submit',
            'btn_submit',
            array(
                'name' => 'submit'
            )
        );
        $this->setupQueries();
        $this->view->form->setRequest($this->getRequest());

        if ($this->view->form->isSubmittedAndValid()) {
            $tree = $this->registry->createQueryTreeForFilter($this->view->form->getValue('query'));
            $this->view->tree = new \Icinga\Web\Widget\FilterBadgeRenderer($tree);
            $view = \Icinga\Module\Monitoring\DataView\HostAndServiceStatus::fromRequest($this->getRequest());
            $cv = new \Icinga\Module\Monitoring\Filter\Backend\IdoQueryConverter($view);
            $this->view->sqlString = $cv->treeToSql($tree);
            $this->view->params = $cv->getParams();
        } else if ($this->getRequest()->getHeader('accept') == 'application/json') {
            $this->getResponse()->setHeader('Content-Type', 'application/json');
            $this->_helper->json($this->parse($this->getRequest()->getParam('query', '')));
        }
    }

    private function setupQueries()
    {
        $this->registry->addDomain(\Icinga\Module\Monitoring\Filter\MonitoringFilter::hostFilter());
    }

    private function parse($text)
    {
        try {
            return $this->registry->getProposalsForQuery($text);
        } catch (\Exception $exc) {
            Logger::error($exc);
        }
    }


}
