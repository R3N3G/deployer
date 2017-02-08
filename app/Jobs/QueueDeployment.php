<?php

namespace REBELinBLUE\Deployer\Jobs;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Auth;
use REBELinBLUE\Deployer\Deployment;
use REBELinBLUE\Deployer\Jobs\QueueDeployment\GroupedCommandListTransformer;
use REBELinBLUE\Deployer\Jobs\QueueDeployment\StepsBuilder;
use REBELinBLUE\Deployer\Project;

/**
 * Generates the required database entries to queue a deployment.
 */
class QueueDeployment extends Job
{
    use DispatchesJobs;

    /**
     * @var Project
     */
    private $project;

    /**
     * @var Deployment
     */
    private $deployment;

    /**
     * @var array
     */
    private $optional;

    /**
     * QueueDeployment constructor.
     *
     * @param Project    $project
     * @param Deployment $deployment
     * @param array      $optional
     */
    public function __construct(Project $project, Deployment $deployment, array $optional = [])
    {
        $this->project    = $project;
        $this->deployment = $deployment;
        $this->optional   = $optional;
    }

    /**
     * Execute the command.
     *
     * @param GroupedCommandListTransformer $transformer
     * @param StepsBuilder                  $steps
     */
    public function handle(GroupedCommandListTransformer $transformer, StepsBuilder $steps)
    {
        $this->setDeploymentStatus();

        $steps->build(
            $transformer->groupCommandsByDeployStep($this->project),
            $this->project,
            $this->deployment->id,
            $this->optional
        );

        $this->dispatch(new DeployProject($this->deployment));
    }

    /**
     * Sets the deployment to pending.
     */
    private function setDeploymentStatus()
    {
        $this->deployment->status     = Deployment::PENDING;
        $this->deployment->started_at = $this->deployment->freshTimestamp();
        $this->deployment->project_id = $this->project->id;

        if (Auth::check()) {
            $this->deployment->user_id = Auth::user()->id;
        } else {
            $this->deployment->is_webhook = true;
        }

        $this->deployment->committer = $this->deployment->committer ?: Deployment::LOADING;
        $this->deployment->commit    = $this->deployment->commit ?: Deployment::LOADING;
        $this->deployment->save();

        $this->deployment->project->status = Project::PENDING;
        $this->deployment->project->save();
    }
}
