<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $u = App\Models\User::first();
    if (!$u)
        throw new Exception('No user');

    $req = Illuminate\Http\Request::create('/api/organizations', 'POST', [
        'name' => 'test_org',
        'uid' => '111111111111',
        'website' => '',
        'location' => '',
        'description' => '',
        'roles' => [
            [
                'name' => 'HOD',
                'permissions' => ['org.view', 'member.invite']
            ]
        ]
    ]);

    $req->setUserResolver(function () use ($u) {
        return $u;
    });

    $c = new App\Http\Controllers\OrganizationController();
    $res = $c->store(App\Http\Requests\Organization\CreateOrganizationRequest::createFrom($req));
    echo "SUCCESS";

} catch (Exception $e) {
    file_put_contents('err.txt', $e->getMessage());
}
