<?php

namespace KanboardTests\units\Model;

defined('DB_FILENAME') or define('DB_FILENAME', ':memory:');

use KanboardTests\units\Base;
use Kanboard\Model\OverviewReportModel;
use Kanboard\Model\TaskModel;
use Kanboard\Model\TaskCreationModel;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\ColumnModel;
use Kanboard\Model\UserModel;
use Kanboard\Model\GroupModel;
use Kanboard\Model\GroupMemberModel;
use Kanboard\Model\TagModel;
use Kanboard\Model\TaskTagModel;

class OverviewReportModelTest extends Base
{
    public function testGetOverviewDataStructure()
    {
        $overviewReportModel = new OverviewReportModel($this->container);
        $data = $overviewReportModel->getOverviewData();

        $this->assertArrayHasKey('sprint', $data);
        $this->assertArrayHasKey('period', $data);
        $this->assertArrayHasKey('kpis', $data);
        $this->assertArrayHasKey('developers', $data);
        $this->assertArrayHasKey('product_leaders', $data);
        $this->assertArrayHasKey('projects', $data);

        $this->assertArrayHasKey('total_tasks', $data['kpis']);
        $this->assertArrayHasKey('concluded_tasks', $data['kpis']);
        $this->assertArrayHasKey('total_points', $data['kpis']);
        $this->assertArrayHasKey('interruption_rate', $data['kpis']);
        $this->assertArrayHasKey('occupancy_rate', $data['kpis']);
    }

    public function testDependencyInjectionRegistration()
    {
        $this->assertInstanceOf('Kanboard\Model\OverviewReportModel', $this->container['overviewReportModel']);
    }

    public function testDeveloperMutualExclusionAndWorkload()
    {
        $userModel = new UserModel($this->container);
        $groupModel = new GroupModel($this->container);
        $groupMemberModel = new GroupMemberModel($this->container);
        $projectModel = new ProjectModel($this->container);
        $columnModel = new ColumnModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        // Ensure Project ID 1 exists
        $projectId = $projectModel->create(['name' => 'Sprint Project']);
        $this->assertEquals(1, $projectId);
        $columnModel->create(1, 'Concluído'); // ID 5

        // Create Groups: Group 2 = Developers, Group 4 = Product Leaders
        $groupModel->create('Group1'); // ID 1
        $groupModel->create('Developers'); // ID 2
        $groupModel->create('Group3'); // ID 3
        $groupModel->create('Product Leaders'); // ID 4

        // Create Users
        $dev1Id = $userModel->create(['username' => 'dev1', 'name' => 'Dev One']);
        $dev2Id = $userModel->create(['username' => 'dev2', 'name' => 'Dev Two']);
        $leaderId = $userModel->create(['username' => 'leader1', 'name' => 'Leader One']);
        $bothId = $userModel->create(['username' => 'both1', 'name' => 'Both Leader And Dev']);

        // Add to groups
        $groupMemberModel->addUser(2, $dev1Id);
        $groupMemberModel->addUser(2, $dev2Id);
        $groupMemberModel->addUser(4, $leaderId);

        // User $bothId is in BOTH Developers and Product Leaders
        $groupMemberModel->addUser(2, $bothId);
        $groupMemberModel->addUser(4, $bothId);

        // Create tasks in project 1
        // Dev1: 2 tasks (score: 5 and 12 = 17)
        $taskCreationModel->create(['title' => 'Task 1', 'project_id' => 1, 'owner_id' => $dev1Id, 'score' => 5, 'column_id' => 5]);
        $taskCreationModel->create(['title' => 'Task 2', 'project_id' => 1, 'owner_id' => $dev1Id, 'score' => 12, 'column_id' => 1]);

        // Dev2: 1 task (score: 8)
        $taskCreationModel->create(['title' => 'Task 3', 'project_id' => 1, 'owner_id' => $dev2Id, 'score' => 8, 'column_id' => 5]);

        // Leader: 1 task (score: 6)
        $taskCreationModel->create(['title' => 'Task 4', 'project_id' => 1, 'owner_id' => $leaderId, 'score' => 6, 'column_id' => 5]);

        // Both: 2 tasks (scores: 4 and 6 = 10)
        $taskCreationModel->create(['title' => 'Task 5', 'project_id' => 1, 'owner_id' => $bothId, 'score' => 4, 'column_id' => 5]);
        $taskCreationModel->create(['title' => 'Task 6', 'project_id' => 1, 'owner_id' => $bothId, 'score' => 6, 'column_id' => 1]);

        $overviewReportModel = new OverviewReportModel($this->container);
        $data = $overviewReportModel->getOverviewData();

        $devIds = array_column($data['developers'], 'id');
        $leaderIds = array_column($data['product_leaders'], 'id');

        // Verify mutual exclusion: $bothId must be in product_leaders, NOT in developers
        $this->assertContains($dev1Id, $devIds);
        $this->assertContains($dev2Id, $devIds);
        $this->assertNotContains($leaderId, $devIds);
        $this->assertNotContains($bothId, $devIds);

        $this->assertContains($leaderId, $leaderIds);
        $this->assertContains($bothId, $leaderIds);

        // Verify Dev stats and ranking
        $this->assertEquals(1, $data['developers'][0]['rank']);
        $this->assertEquals($dev1Id, $data['developers'][0]['id']);
        $this->assertEquals(17.0, $data['developers'][0]['total_points']);
        $this->assertEquals(2, $data['developers'][0]['total_tasks']);
        $this->assertEquals(1, $data['developers'][0]['concluded_tasks']);
        $this->assertEquals(8.5, $data['developers'][0]['avg_complexity']);

        $this->assertEquals(2, $data['developers'][1]['rank']);
        $this->assertEquals($dev2Id, $data['developers'][1]['id']);
        $this->assertEquals(8.0, $data['developers'][1]['total_points']);
        $this->assertEquals(1, $data['developers'][1]['total_tasks']);
        $this->assertEquals(1, $data['developers'][1]['concluded_tasks']);
        $this->assertEquals(8.0, $data['developers'][1]['avg_complexity']);

        // Verify Leader stats including avg_complexity
        $leadersById = [];
        foreach ($data['product_leaders'] as $l) {
            $leadersById[$l['id']] = $l;
        }

        $this->assertEquals(6.0, $leadersById[$leaderId]['total_points']);
        $this->assertEquals(1, $leadersById[$leaderId]['total_tasks']);
        $this->assertEquals(6.0, $leadersById[$leaderId]['avg_complexity']);

        $this->assertEquals(10.0, $leadersById[$bothId]['total_points']);
        $this->assertEquals(2, $leadersById[$bothId]['total_tasks']);
        $this->assertEquals(5.0, $leadersById[$bothId]['avg_complexity']);

        // Global KPIs
        $this->assertEquals(6, $data['kpis']['total_tasks']);
        $this->assertEquals(4, $data['kpis']['concluded_tasks']);
        $this->assertEquals(41.0, $data['kpis']['total_points']);
        $this->assertEquals(2, $data['kpis']['total_devs']);
        $this->assertEquals(2, $data['kpis']['active_devs']);
        $this->assertEquals(100, $data['kpis']['occupancy_rate']);
    }

    public function testPortfolioStatsAndProductSorting()
    {
        $projectModel = new ProjectModel($this->container);
        $columnModel = new ColumnModel($this->container);
        $taskCreationModel = new TaskCreationModel($this->container);

        $projectId = $projectModel->create(['name' => 'Sprint Project']);
        $this->assertEquals(1, $projectId);
        $columnModel->create(1, 'Concluído'); // ID 5

        // Insert tags into tags table
        $this->container['db']->table('tags')->insert(['id' => 233, 'name' => 'CPB Provas', 'project_id' => 0]);
        $this->container['db']->table('tags')->insert(['id' => 243, 'name' => 'DevOps', 'project_id' => 0]);

        // Create tasks with tags
        // Task 1: ProjectTag 233 (CPB_PROVAS), ProductTag 233 (CPB_PROVAS), Concluded (5), Score 8
        $t1 = $taskCreationModel->create(['title' => 'CPB Provas Feature', 'project_id' => 1, 'score' => 8, 'column_id' => 5]);
        $this->container['db']->table('task_has_tags')->insert(['task_id' => $t1, 'tag_id' => 233]);

        $overviewReportModel = new OverviewReportModel($this->container);
        $data = $overviewReportModel->getOverviewData();

        $this->assertNotEmpty($data['projects']);
        
        $projectsById = [];
        foreach ($data['projects'] as $p) {
            $projectsById[$p['id']] = $p;
        }

        // Check CPB_PROVAS (233)
        $this->assertArrayHasKey(233, $projectsById);
        $cpbProvas = $projectsById[233];
        $this->assertTrue($cpbProvas['has_activity']);
        $this->assertEquals(1, $cpbProvas['total_tasks']);
        $this->assertEquals(1, $cpbProvas['concluded_tasks']);
        $this->assertEquals(8.0, $cpbProvas['total_points']);

        // Check that only products with activity are returned
        $this->assertCount(1, $cpbProvas['products']);
        $firstProduct = $cpbProvas['products'][0];
        $this->assertTrue($firstProduct['has_activity']);
        $this->assertEquals('CPB Provas', $firstProduct['name']);
        $this->assertEquals(1, $firstProduct['total_tasks']);

        // Check project without tasks has empty products list
        $projectWithoutTasks = array_values(array_filter($data['projects'], fn($p) => $p['id'] !== 233))[0];
        $this->assertEmpty($projectWithoutTasks['products']);
    }

    public function testRenderOverviewTemplate()
    {
        $overviewReportModel = new OverviewReportModel($this->container);
        $data = $overviewReportModel->getOverviewData();

        $html = $this->container['template']->render('report/overview', $data);

        $this->assertStringContainsString('Visão Geral da Sprint', $html);
        $this->assertStringContainsString('Concluídos', $html);
        $this->assertStringContainsString('Total de Pontos', $html);
        $this->assertStringContainsString('Taxa de Interrupção', $html);
        $this->assertStringContainsString('Ocupação da Equipe', $html);
        $this->assertStringContainsString('Desenvolvedores', $html);
        $this->assertStringContainsString('Líderes de Produto', $html);
        $this->assertStringContainsString('Portfólio de Projetos e Produtos', $html);
        $this->assertStringContainsString('Planejados', $html);
        $this->assertStringContainsString('Nenhum produto trabalhado nesta sprint.', $html);
    }
}
