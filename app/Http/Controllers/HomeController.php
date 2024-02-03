<?php

namespace App\Http\Controllers;

use App\Models\course_group;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\agency;
use App\Models\branch;
use App\Models\lesson;
use App\Models\department;
use App\Models\progress;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\course;
use App\Models\quiz;
use App\Notifications\MessageNotification;
use App\Models\Test;
use Illuminate\Support\Facades\Log;
use App\Models\Activitylog;
use App\Models\user_request;
use Auth;
use PDF;
use TCPDF as TCPDF;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class HomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function courseDetail(Request $request, $id) {
        $lessons = lesson::where("course", $id)->get();
        $course = course::find($id);
        if ($request->user()->hasAnyRole(['admin','staff'])) {
            $quizzes = quiz::all();
        } else {
            $quizzes = quiz::where('create_by', $request->user()->id)->get();
        }
        $tested = Test::where('tester', $request->user()->id)->orderBy('id', 'desc')->get();

        Log::channel('activity')->info('User '. $request->user()->name .' visited course detail',
        [
            'user_id' => auth()->id(),
            'content' => $course,
        ]);
        return view("page.courses.course-detail", compact("id", "lessons", "course", "quizzes", 'tested'));
    }

    public function allCourse(Request $request) {
        $courses = course::where('permission->all', "true")->paginate(12);

        Log::channel('activity')->info('User '. $request->user()->name .' visited all course',
        [
            'user_id' => auth()->id(),
        ]);
        return view("page.courses.allcourse", compact("courses"));
    }

    public function dashboard(Request $request) {
        $courses = course::all();
        $dpms = department::all();
        $tests = Test::all();
        $activitys = Activitylog::orderBy('id', 'desc')->get();
        $courseDel = course::onlyTrashed()->get();
        $quizDel = quiz::onlyTrashed()->get();

        $agns = agency::all();
        $brns = branch::all();
        $roles = Role::all();
        $permissions = Permission::all();
        // $record->restore();

        Log::channel('activity')->info('User '. $request->user()->name .' visited dashboard',
        [
            'user_id' => auth()->id(),
        ]);
        if ($request->user()->hasRole('admin')) {
            return view("page.dashboard", compact('courses', 'dpms', 'tests', 'activitys', 'courseDel', 'quizDel', 'agns', 'brns', 'roles', 'permissions'));
        } else {
            Auth::logout();
            return redirect('/');
        }
    }

    public function main(Request $request) {
        $courses = course::latest()->take(6)->get();
        $dpms = department::all();
        if ($request->user()->role == "new") {
            return redirect()->route('home');
        } else {
            $allcourses = course::where('permission->all', "true")->take(8)->get();
            if ($request->user()->hasAnyRole('admin', 'staff')) {
                $dpmcourses = course::where('permission->dpm', "true")->take(8)->get();
            } else {
                $dpmcourses = course::where('permission->dpm', "true")
                 ->where(function ($query) use ($request) {
                     $query->Where('dpm', $request->user()->dpm);
                 })->orWhere("studens", 'LIKE' , '%"'.$request->user()->id.'"%')->take(8)->get();
            }

            Log::channel('activity')->info('User '. $request->user()->name .' visited main page',
            [
                'user' => $request->user(),
            ]);
            return view("page.main", compact("allcourses", "dpms", "dpmcourses"));
        }
    }

    public function home(Request $request) {
        $courses = course::whereIn("id", $request->user()->courses)->latest()->take(6)->get();
        $user = $request->user();

        Log::channel('activity')->info('User '. $request->user()->name .' visited Home page',
        [
            'user' => $request->user(),
        ]);
        if ($request->user()->hasRole('new')) {
            return view("page.home", compact("courses", 'user'));
        } else {
            Auth::logout();
            return redirect('/');
        }
    }

    public function allUsers(Request $request) {
        $users = User::all();
        $agns = agency::all();
        $dpms = department::all();
        $brns = branch::all();
        $roles = Role::all();
        $permissions = Permission::all();
        $courses = course::all();

        Log::channel('activity')->info('User '. $request->user()->name .' visited alluser',
        [
            'user_id' => $request->user(),
        ]);
        if ($request->user()->hasAnyRole('admin', 'staff')) {
            return view("page.users.allusers", compact("users","dpms","agns","brns", "roles", "permissions", "courses"));
        } else {
            Auth::logout();
            return redirect('/');
        }
    }

    public function userDetail(Request $request, $id) {
        $user = User::find($id);
        $agns = agency::all();
        $dpms = department::all();
        $brns = branch::all();
        $roles = Role::all();
        $permissions = Permission::all();
        $courses = course::all();
        $groups = course_group::all();
        $ucourse = course::whereIn("id", $user->courses ?? [])->get();
        $tests = Test::where('tester', $user->id)->orderBy('id', 'desc')->get();
        $ownCourse = course::where('teacher', $user->id)->orderBy('id', 'desc')->get();

        Log::channel('activity')->info('User '. $request->user()->name .' visited userDetail',
        [
            'content' => $id,
            'user' => $request->user(),
        ]);
        if ($request->user()->hasAnyRole('admin', 'staff')) {
            return view("page.users.userDetail", compact("id","user", "groups", "roles", "permissions","dpms","agns","brns", "courses", 'ucourse', 'tests', 'ownCourse'));
        } else {
            Auth::logout();
            return redirect('/');
        }
    }

    public function requestAll(Request $request) {
        if ($request->user()->hasAnyRole('admin', 'staff')) {
            $requests = user_request::orderBy('id', 'desc')->get();
        } else {
            $requests = user_request::where('user', $request->user()->id)->orderBy('id', 'desc')->get();
        }

        Log::channel('activity')->info('User '. $request->user()->name .' visited requestAll',
        [
            'user' => $request->user(),
        ]);
        return view("page.requestAll", compact('requests'));
    }

    public function ownCourse(Request $request) {
        $courses = course::where("teacher", auth()->id())->get();
        $groups = course_group::where('by', auth()->id())->get();

        Log::channel('activity')->info('User '. $request->user()->name .' visited ownCourse',
        [
            'user' => $request->user(),
        ]);
        if ($request->user()->hasAnyRole('admin', 'staff', 'teacher')) {
            return view("page.courses.own-course", compact('courses', 'groups'));
        } else {
            Auth::logout();
            return redirect('/');
        }
    }

    public function classroom(Request $request) {
        // $courses = course::where('permission->dpm', "true")
        //          ->where(function ($query) use ($request) {
        //              $query->where("studens", 'LIKE' , '%"'.$request->user()->id.'"%')
        //                     ->orWhere('dpm', $request->user()->dpm);
        //          })->paginate(12);
        // $courses = course::where("studens", 'LIKE' , '%"'.$request->user()->id.'"%')->orWhere('dpm', $request->user()->dpm)->get();

        if ($request->user()->hasAnyRole('admin', 'staff')) {
            $courses = course::where('permission->dpm', "true")->orWhere("studens", 'LIKE' , '%"'.$request->user()->id.'"%')->paginate(12);
        } else {
            $courses = course::where("studens", 'LIKE' , '%"'.$request->user()->id.'"%')
                 ->orWhere(function ($query) use ($request) {
                     $query->where('permission->dpm', "true")
                            ->Where('dpm', $request->user()->dpm);
                 })->paginate(12);
        }

        $dpms = department::all();

        Log::channel('activity')->info('User '. $request->user()->name .' visited classroom',
        [
            'user' => $request->user(),
        ]);
        return view("page.courses.myclassroom", compact("courses","dpms"));
    }

    public function sendMessage(Request $request)
    {
        date_default_timezone_set('Asia/Bangkok');

        try {
            $text = $request->input('text');
            $noticText = [
                'user' => $request->user()->id,
                'content' => $text,
                'date'=> date('Y-m-d H:i:s'),
                'status'=> 'wait',
            ];
            // Validation and logic for the message

            // Find staff to notify
            $staffUsers = User::whereIn('role', ['admin','staff'])->get();
            // Send notification to each staff user
            foreach ($staffUsers as $staffUser) {
                $staffUser->notify(new MessageNotification($noticText));
            }

            Activitylog::create([
                'user' => auth()->id(),
                'module' => 'Notification',
                'content' => $text,
                'note' => 'store',
            ]);
            Log::channel('activity')->info('User '. $request->user()->name .' sendMessage',
            [
                'user' => $request->user(),
                'message' => $text,
            ]);
            return response()->json(['success' => $request->all()]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()]);
        }
    }

    public function markAsRead($id)
    {
        try {
            $notification = user_request::find($id);

            $alert = $notification->alert;
            $filteredData = array_filter($alert, function ($value) {
                return $value != auth()->user()->id;
            });

            $result = array_values($filteredData);

            $notification->alert = json_encode($result);
            $notification->save();

            return response()->json(['status' => 'success', 'response' => $notification->alert], 200);
        } catch (\Throwable $th) {
            return response()->json(['status' => 'error', 'error' => $th->getMessage()], 404);
        }
    }

    public function noticSuccess($id)
    {
        $notification = auth()->user()->notifications->find($id);

        if ($notification) {
            // Decode the data field into an array, modify it, and re-encode it as JSON
            $data = $notification->data;
            $data['status'] = 'success';
            $notification->data = $data;

            // Save the modified notification back to the database
            $notification->save();

            Activitylog::create([
                'user' => auth()->id(),
                'module' => 'notification',
                'content' => $notification->id,
                'note' => 'set success',
            ]);
            return response()->json(['status' => 'success'], 200);
        }

        return response()->json(['status' => 'error'], 404);
    }

    public function previewPDF($type) {
        $data = [];
        $agn = agency::find(auth()->user()->agency);
        if ($type == 'course') {
            $courses = course::orderBy('id', 'desc')->get();
            $data = ['data' => $courses, 'type' => $type, 'agn' => $agn];
        } elseif ($type == 'test') {
            $tests = Test::orderBy('id', 'desc')->get();
            $data = ['data' => $tests, 'type' => $type, 'agn' => $agn];
        } elseif ($type == 'activity') {
            $activitys = Activitylog::orderBy('id', 'desc')->get();
            $data = ['data' => $activitys, 'type' => $type, 'agn' => $agn];
        }


        // Load the view and set paper orientation to landscape
        $pdf = PDF::loadView('page.exports.exportData', $data)
                  ->setPaper('a4', 'landscape')->setOptions(['encoding' => 'utf-8']); // Set the paper size to A4 and orientation to landscape

        return $pdf->stream('KST_Data.pdf');
    }


}
