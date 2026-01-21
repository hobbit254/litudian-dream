<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Mail\EmailVerificationMail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    /**
     * Method used to fetch all paginated data
     * @param Request $request
     * @return JsonResponse
     */
    public function allUsers(Request $request): JsonResponse
    {

        $perPage = $request->input('per_page', 15);
        $startDate = $request->input('start_date', Carbon::today());
        $endDate = $request->input('end_date', Carbon::today());

        $query = User::withTrashed();

        $query->join('user_roles', 'user_roles.user_id', '=', 'users.id');
        $query->join('roles', 'roles.id', '=', 'user_roles.role_id');
        $query->select('users.*', 'roles.role_name');
        $query->when($startDate, function ($q) use ($startDate) {

            $q->whereDate('users.created_at', '>=', $startDate);
        });
        $query->when($endDate, function ($q) use ($endDate) {

            $q->whereDate('users.created_at', '<=', $endDate);
        });
        $query->orderBy('users.created_at', 'desc');
        $usersPaginator = $query->paginate($perPage);

        $data = $usersPaginator->items();

        $meta = [
            'total' => $usersPaginator->total(),
            'per_page' => $usersPaginator->perPage(),
            'current_page' => $usersPaginator->currentPage(),
            'last_page' => $usersPaginator->lastPage(),
            'from' => $usersPaginator->firstItem(),
            'to' => $usersPaginator->lastItem(),
        ];

        return ResponseHelper::success(['data' => $data, 'meta' => $meta], 'Users retrieved successfully.', 200);
    }

    /**
     * Method to create a new user
     * @param Request $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'string', 'min:36', 'max:36']]);

        $verificationToken = Str::random(60);
        try {
            $role = Role::where('uuid', $request->get('role_id'))->first();

            if (!$role) {
                // Handle the case where the provided UUID doesn't exist
                return ResponseHelper::error([], 'Invalid role identifier.', 404);
            }
            DB::beginTransaction();
            $user = User::create([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'password' => Hash::make($request->get('password')),
                'verification_token' => $verificationToken,
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);
            DB::commit();

            Mail::to($user->email)->send(new EmailVerificationMail($user));

            return ResponseHelper::success($user->toArray(),
                'Registration successful. Please check your email to verify your account.', 201);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            DB::rollBack();
            return ResponseHelper::error([], 'An exception was caught when trying to save the data to db', 500);
        }
    }

    /**
     * Method to update the users information
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::where('uuid', $request->get('uuid'))->first();
            if (!$user) {
                return ResponseHelper::error([], 'User not found.', 404);
            }
            $user_role = UserRole::where('user_id', $user->id)->first();
            if ($request->has('name')) {
                $user->name = $request->get('name');
            }
            if ($request->has('email')) {
                $user->email = $request->get('email');
            }
            if ($request->has('active')) {
                $user->active = $request->get('active') ? 1 : 0;
            }
            if ($request->has('role_id')) {
                $role = Role::where('uuid', $request->get('role_id'))->first();
                $user_role->role_id = $role->id;
            }
            if ($request->has('password')) {
                $user->password = Hash::make($request->get('password'));
            }
            $user->save();
            $user_role->save();
            DB::commit();
            return ResponseHelper::success($user->toArray(), 'User updated successfully.', 200);

        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            DB::rollBack();
            return ResponseHelper::error([], 'An exception was caught when trying to update the data to db', 500);
        }
    }

    /**
     * Method used to soft-delete a user
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(Request $request): JsonResponse
    {
        $request->validate([
            'uuid' => ['required', 'string'],
        ]);
        $user = User::where('uuid', $request->get('uuid'))->first();

        if (!$user) {
            return ResponseHelper::error([], 'User not found.', 404);
        }

        $user->delete();
        return ResponseHelper::success($user->toArray(), 'User deleted successfully.', 200);
    }

    /**
     * Method used to activate or deactivate a user
     * @param Request $request
     * @return JsonResponse
     */
    public function activate_deactivate(Request $request): JsonResponse
    {
        $request->validate([
            'uuid' => ['required'],
            'status' => ['required', 'numeric'],
        ]);
        $user = User::where('uuid', $request->get('uuid'))->first();
        if (!$user) {
            return ResponseHelper::error([], 'User not found.', 404);
        }
        $message = 'User activated successfully.';
        if ($request->get('status') === 1) {
            $user->active = 1;
        } else {
            $user->active = 0;
            $message = 'User deactivated successfully.';
        }
        $user->save();
        return ResponseHelper::success($user->toArray(), $message, 200);
    }

    /**
     * Method used to fetch the user data
     * @param string $uuid
     * @return JsonResponse
     */
    public function getByUuid(Request $request): JsonResponse
    {
        $uuid = $request->get('uuid');
        $user = DB::table('users')
            ->select('users.*', 'roles.role_name')
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('users.uuid', $uuid)
            ->first();
        if (!$user) {
            return ResponseHelper::error([], 'User not found.', 404);
        }
        return ResponseHelper::success((array)$user, 'User retrieved successfully.', 200);
    }


}
