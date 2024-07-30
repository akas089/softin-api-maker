<?php

class UserController extends Controller
{
    public function index()
    {
        $users = ['user1', 'user2']; // Example data
        $this->templateRender('users.index', ['users' => $users]);
    }

    public function show($request, $payload, $parameters)
    {
        $user = ['id' => $parameters->id, 'name' => 'User' . $parameters->id]; // Example data
        $this->templateRender('users.show', ['user' => $user]);
    }

    public function store($request, $payload, $parameters)
    {
        echo json_encode(['created' => $payload]);
    }

    public function update($request, $payload, $parameters)
    {
        echo json_encode(['id' => $parameters->id, 'updated' => $payload]);
    }

    public function destroy($request, $payload, $parameters)
    {
        echo json_encode(['deleted' => $parameters->id]);
    }
}
