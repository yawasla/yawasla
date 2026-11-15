<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Admin\Controller;

use Throwable;
use Yawasla\Controller\AbstractAdminController;
use Yawasla\Core\ModuleException;
use Yawasla\Core\ModuleManager;
use Yawasla\Core\Modules;
use Yawasla\Core\Trash;
use Yawasla\Form\Form;
use Yawasla\Http\NotFoundException;
use Yawasla\Http\Route;
use Yawasla\Http\Router;
use Yawasla\Http\Session;
use Yawasla\I18n\Translator;

#[Route('/admin/modules', auth: 'admin')]
final class ModulesController extends AbstractAdminController
{
    #[Route('', name: 'admin_modules')]
    public function index(): string
    {
        $modules = Modules::getInstance();
        $modules->discover();
        $list = array_values($modules->all());
        usort($list, fn (array $a, array $b): int => [!$a['core'], $a['name']] <=> [!$b['core'], $b['name']]);
        $locale = Translator::getInstance()->locale();
        $dependents = [];

        foreach ($list as $module) {
            foreach ($module['requires'] as $required) {
                $dependents[strtolower($required)][] = $module['name'];
            }
        }

        return $this->render('admin/modules.twig', [
            'modules' => array_map(fn (array $module): array => $module + ['message' => Modules::reason($module), 'text' => $module['description'][$locale] ?? $module['description']['en'] ?? '', 'dependents' => $dependents[$module['id']] ?? []], $list),
            'update' => $modules->updatePending(),
            'trash' => count(Trash::list()),
        ]);
    }

    #[Route('/trash', name: 'admin_modules_trash')]
    public function trash(): string
    {
        return $this->render('admin/trash.twig', [
            'entries' => array_map(fn (array $item): array => [
                'entry' => $item['entry'],
                'name' => $item['name'],
                'date' => $item['date']->format('Y-m-d H:i'),
                'deleted' => $item['date']->modify('+' . Trash::DAYS . ' days')->format('Y-m-d H:i'),
            ], Trash::list()),
        ]);
    }

    #[Route('/trash/{entry}/restore', name: 'admin_modules_restore', methods: ['POST'])]
    public function restore(string $entry): string
    {
        return $this->change(fn (ModuleManager $manager) => $manager->restore($entry), '%module% restored: enable it to use it.', '');
    }

    #[Route('/{module}/enable', name: 'admin_modules_enable', methods: ['POST'])]
    public function enable(string $module): string
    {
        return $this->change(fn (ModuleManager $manager) => $manager->enable($module), '%module% enabled.', $module);
    }

    #[Route('/{module}/disable', name: 'admin_modules_disable', methods: ['POST'])]
    public function disable(string $module): string
    {
        return $this->change(fn (ModuleManager $manager) => $manager->disable($module), '%module% disabled.', $module);
    }

    #[Route('/{module}/uninstall', name: 'admin_modules_uninstall', methods: ['GET'])]
    public function uninstall(string $module): string
    {
        $found = $this->extension($module);

        if ($found['wanted']) {
            Session::getInstance()->flash('danger', Translator::getInstance()->trans('Disable %module% first.', ['%module%' => $found['name']]));

            return $this->redirect(Router::getInstance()->path('admin_modules'), 303);
        }

        return $this->render('admin/uninstall.twig', ['module' => $found]);
    }

    #[Route('/{module}/uninstall', name: 'admin_modules_uninstall_confirm', methods: ['POST'])]
    public function uninstallConfirm(string $module): string
    {
        $found = $this->extension($module);

        return $this->change(fn (ModuleManager $manager) => $manager->uninstall($found['name']), '%module% uninstalled: its folder is in data/trash for 7 days.', $found['name']);
    }

    #[Route('/update', name: 'admin_modules_update', methods: ['POST'])]
    public function update(): string
    {
        return $this->change(fn (ModuleManager $manager) => $manager->finishUpdate(), 'Update finished.', '');
    }

    /** @param callable(ModuleManager): mixed $action */
    private function change(callable $action, string $success, string $module): string
    {
        if (Form::validToken($_POST)) {
            $session = Session::getInstance();
            $translator = Translator::getInstance();

            try {
                $result = $action(new ModuleManager());
                $session->flash('success', $translator->trans($success, ['%module%' => is_string($result) ? $result : $module]));
            } catch (ModuleException $e) {
                $params = $e->reason === null ? $e->params : ['%reason%' => $translator->trans(...$e->reason)] + $e->params;
                $session->flash('danger', $translator->trans($e->getMessage(), $params));
            } catch (Throwable $e) {
                trigger_error((string) $e, E_USER_WARNING);
                $session->flash('danger', $translator->trans('The change could not be made. Details are in data/logs/php.log.'));
            }
        }

        return $this->redirect(Router::getInstance()->path('admin_modules'), 303);
    }

    /** @return array<string, mixed> */
    private function extension(string $name): array
    {
        $module = preg_match(Modules::NAME, $name) ? Modules::getInstance()->find($name) : null;

        if ($module === null || $module['core']) {
            throw new NotFoundException();
        }

        return $module;
    }
}
