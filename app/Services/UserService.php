<?php namespace App\Services;

use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\UserRepository;

class UserService extends BaseService
{
    protected $userRepo;
    protected $datatableService;

    public function __construct(UserRepository $userRepo, DatatableService $datatableService)
    {
        $this->userRepo = $userRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->userRepo;
    }

    /*
    public function save()
    {
        return null;
    }
    */

    public function getDatatable($accountId)
    {
        $query = $this->userRepo->find($accountId);

        return $this->createDatatable(ENTITY_USER, $query, false);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'first_name',
                function ($model) {
                    return $model->public_id ? link_to('users/'.$model->public_id.'/edit', $model->first_name.' '.$model->last_name) : ($model->first_name.' '.$model->last_name);
                }
            ],
            [
                'email',
                function ($model) {
                    return $model->email;
                }
            ],
            [
                'confirmed',
                function ($model) {
                    if (!$model->public_id) {
                        return self::getStatusLabel(USER_STATE_ADMIN);
                    } elseif ($model->deleted_at) {
                        return self::getStatusLabel(USER_STATE_DISABLED);
                    } elseif ($model->confirmed) {
                        return self::getStatusLabel(USER_STATE_ACTIVE);
                    } else {
                        return self::getStatusLabel(USER_STATE_PENDING);
                    }
                }
            ],
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                uctrans('texts.edit_user'),
                function ($model) {
                    return URL::to("users/{$model->public_id}/edit");
                },
                function ($model) {
                    return $model->public_id;
                }
            ],
            [
                uctrans('texts.send_invite'),
                function ($model) {
                    return URL::to("send_confirmation/{$model->public_id}");
                },
                function ($model) {
                    return $model->public_id && ! $model->confirmed;
                }
            ]
        ];
    }

    private function getStatusLabel($state)
    {
        $label = trans("texts.{$state}");
        $class = 'default';
        switch ($state) {
            case USER_STATE_PENDING:
                $class = 'info';
                break;
            case USER_STATE_ACTIVE:
                $class = 'primary';
                break;
            case USER_STATE_DISABLED:
                $class = 'warning';
                break;
            case USER_STATE_ADMIN:
                $class = 'success';
                break;
        }
        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }

}