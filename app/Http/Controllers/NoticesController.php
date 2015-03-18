<?php namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Flash;
use App\Mail;
use App\Notice;
use App\Provider;
use App\User;

use Illuminate\Http\Request;
use Illuminate\Mail\Mailer;

class NoticesController extends Controller {

    /*
    * Create a new notices controller instance
    */

    public function __construct()
    {
        $this->middleware('auth');
    }

    /*
    * Show all notices
    */

	public function index()
    {
        $notices = \Auth::user()->notices()->latest()->get();

        return view('notices.index', compact('notices'));
    }

    /*
    * Show a page to create a new notice
    */

    public function create()
    {
        // get list of providers
        $providers = Provider::lists('name', 'id');
        //$providers = [['name' => 'YouTube', 'copyright_email' => 'copyright@YouTubeFalse.com']];

        // load a view to create a new notice
        return view('notices.create', compact('providers'));
    }

    /**
     * Ask the user to confirm the DMCA to be delivered.
     * @param  Requests\PrepareNoticeRequest $request
     * @return 
     */
    
    public function confirm(Requests\PrepareNoticeRequest $request)
    {

        $template = $this->compileDmcaTemplate($data = $request->all());

        session()->flash('dmca', $data);

        return view('notices.confirm', compact('template'));
    }

    /**
     * Store a new DMCA notice.
     * @param  Request $request
     * @return Redirect
     */
    
    public function store(Request $request)
    {

        $notice = $this->createNotice($request);

        // send an email
        \Mail::queue(['html' => 'emails.dmca'], compact('notice'), function($message) use ($notice) {
            $message->from($notice->getOwnerEmail())
                    ->to($notice->getRecipientEmail())
                    ->subject('DMCA Notice');
        });

        \Flash::message('Your DMCA notice has been sent.');
        return redirect('notices');
    }

    /**
     *  Mark the notices that have been removed by the content provider.
     * @param  $noticeID 
     * @param  Request $request
     * @return 
     */
    
    public function update($noticeID, Request $request)
    {
        $isRemoved = $request->has('content_removed');

        Notice::findOrFail($noticeID)
               ->update(['content_removed' => $isRemoved]);
        
        return redirect()->back();
    }

    /**
     * Create and persist a new DMCA notice.
     * @param  Request $request 
     */
    
    private function createNotice(Request $request)
    {
        $data = session()->get('dmca'); 

        $notice = Notice::open($data)->useTemplate($request->input('template'));

        $notice = \Auth::user()->notices()->save($notice);

        return $notice;

    }

    /**
     * Compile the DMCA template from the form data.
     * @param  $data [data from the form]
     * @return [returns view with data]
     */
    
    public function compileDmcaTemplate($data)
    {

        $data = $data + [
            'name' => \Auth::user()->name,
            'email' => \Auth::user()->email,
        ];

        return view()->file(app_path('Http/Templates/dmca.blade.php'), $data);

    }

}
