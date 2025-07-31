<?php
namespace App\Http\Controllers\ApiAdmin;
use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Models\User;
use Illuminate\Http\Request;


class AdminUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    
            // عرض جميع المستخدمين (باستثناء الأدمن نفسه مثلاً)
            public function index()
            {
                // لو حابب تستثني الأدمن الحالي أو تحدد دور معين، ممكن تضيف شرط هنا
                $users = User::select('id', 'first_name', 'last_name', 'phone', 'email','location','profile_image')
                    ->where('role', 'user')  // مثلا فقط المستخدمين العاديين
                    ->get();
        
                return Response::Success($users, 'Users retrieved successfully');
            }
        





            // حذف مستخدم بواسطة الـ id
            public function destroy($id)
            {
                $user = User::find($id);
        
                if (!$user) {
                    return Response::Error('User not found', 404);
                }
        
                // مثلا ما تحذف الأدمنين
                if ($user->role === 'admin') {
                    return Response::Error('Cannot delete admin user', 403);
                }
        
                $user->delete();
        
                return Response::Success(null, 'User deleted successfully');
            }
        
        
















    

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
   

    /**
     * Show the form for editing the specified resource.
     */
    

    /**
     * Update the specified resource in storage.
     */
   

    /**
     * Remove the specified resource from storage.
     */
    
}
