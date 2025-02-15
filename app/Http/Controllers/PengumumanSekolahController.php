<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Murid;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\PengumumanSekolah;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\MailService;
use Illuminate\Support\Facades\Storage;

class PengumumanSekolahController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }

    public function pengumuman_sekolah()
    {
        ActivityLog::create([
            'action' => 'create',
            'user_id' => Session::get('id'), // ID pengguna yang sedang login
            'description' => 'User Masuk ke Pengumuman User.',
        ]);

        $pengumuman = DB::table('pengumuman_sekolah')
            ->join('user', 'user.id_user', '=', 'pengumuman_sekolah.id_user')
            ->select(
                'user.username',
                'pengumuman_sekolah.judul_pengumuman_sekolah',
                'pengumuman_sekolah.file_pengumuman_sekolah',
                'pengumuman_sekolah.isi_pengumuman',
                'pengumuman_sekolah.tgl_buat',
                'pengumuman_sekolah.id_pengumuman_sekolah',
                'pengumuman_sekolah.id_user',
            )
            ->get();
        $jurusan = Jurusan::all(); // Ambil semua jurusan
        $kelas = Kelas::all();

        echo view('header');
        echo view('menu');
        echo view('pengumuman_sekolah', compact('pengumuman', 'jurusan', 'kelas'));
        echo view('footer');
    }


    public function buat_pengumuman(Request $request)
    {
        ActivityLog::create([
            'action' => 'create',
            'user_id' => Session::get('id'),
            'description' => 'User Membuat Pengumuman Umum.',
        ]);

        try {
            $request->validate([
                'judul_pengumuman_sekolah' => 'required',
                'isi_pengumuman' => 'required',
            ]);

            // Mendapatkan id_user dari session
            $id_user = Session::get('id');

            // Simpan data ke tabel surat
            $pengumuman_sekolah = new PengumumanSekolah();
            $pengumuman_sekolah->judul_pengumuman_sekolah = $request->input('judul_pengumuman_sekolah');
            $pengumuman_sekolah->isi_pengumuman = $request->input('isi_pengumuman');
            $pengumuman_sekolah->tgl_buat = Carbon::now();
            $pengumuman_sekolah->id_user = $id_user;



            // Simpan ke database
            $pengumuman_sekolah->save();

            // Redirect ke halaman lain
            return redirect()->back()->with('success', 'Pengumuman berhasil ditambahkan.');
        } catch (\Exception $e) {
            // Log error detail
            Log::error('Gagal menyimpan pengumuman: ' . $e->getMessage());

            // Redirect kembali dengan pesan kesalahan
            return redirect()->back()->withErrors(['msg' => 'Gagal menambahkan pengumuman. Silakan coba lagi.']);
        }
    }

    public function update(Request $request, $id)
    {
        ActivityLog::create([
            'action' => 'create',
            'user_id' => Session::get('id'), // ID pengguna yang sedang login
            'description' => 'User Mengatur Pengumuman Umum.',
        ]);

        // Log data yang diterima
        Log::info('Update Pengumuman Request', [
            'all_data' => $request->all(),
            'route_id' => $id
        ]);

        // Validasi input
        $validatedData = $request->validate([
            'id_pengumuman_sekolah' => 'required|exists:pengumuman_sekolah,id_pengumuman_sekolah',
            'judul_pengumuman_sekolah' => 'required|string|max:255',
            'isi_pengumuman' => 'required|string',
            'file_pengumuman_sekolah' => 'nullable|file|mimes:pdf,doc,docx,txt,jpg,jpeg,png|max:10240',
        ]);

        try {
            // Cari pengumuman berdasarkan ID
            $pengumuman = PengumumanSekolah::findOrFail($request->input('id_pengumuman_sekolah'));

            // Update data teks
            $pengumuman->judul_pengumuman_sekolah = $request->input('judul_pengumuman_sekolah');
            $pengumuman->isi_pengumuman = $request->input('isi_pengumuman');

            // Proses upload file
            if ($request->hasFile('file_pengumuman_sekolah')) {
                $file = $request->file('file_pengumuman_sekolah');

                // Hapus file lama jika ada
                if ($pengumuman->file_pengumuman_sekolah) {
                    $oldFilePath = public_path('uploads/' . $pengumuman->file_pengumuman_sekolah);

                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                // Generate nama file baru
                $fileName = time() . '_' . $file->getClientOriginalName();

                // Pindahkan file
                $file->move(public_path('uploads'), $fileName);

                // Update nama file
                $pengumuman->file_pengumuman_sekolah = $fileName;
            }

            // Simpan perubahan
            $pengumuman->save();

            return redirect()->route('pengumuman_umum')->with('success', 'Pengumuman berhasil diperbarui.');

        } catch (\Exception $e) {
            Log::error('Error Update Pengumuman', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->withErrors([
                'unexpected' => 'Terjadi kesalahan: ' . $e->getMessage()
            ]);
        }
    }


    public function pengumuman_sekolah_destroy($id)
    {
        ActivityLog::create([
            'action' => 'create',
            'user_id' => Session::get('id'), // ID pengguna yang sedang login
            'description' => 'User Menghapus Pengumuman Sekolah.',
        ]);

        // Cari data user berdasarkan ID
        $pengumuman_sekolah = PengumumanSekolah::findOrFail($id);

        // Hapus data user (soft delete)
        $pengumuman_sekolah->delete();

        // Redirect dengan pesan sukses
        return redirect()->back()->with('success', 'Data user berhasil dihapus');
    }

    public function sendEmail(Request $request)
    {
        // Log awal untuk request data
        Log::info('Request received:', $request->all()); // Log semua input (kecuali file)

        // Validasi request
        $validated = $request->validate([
            'email' => 'required|email',
            'judul_pengumuman_sekolah' => 'required|string',
            'isi_pengumuman' => 'required|string',
            'file_pengumuman_sekolah' => 'nullable|file|max:10240', // Maks 10MB
        ]);

        // Cek apakah file ada dalam request
        if ($request->hasFile('file_pengumuman_sekolah')) {
            $file = $request->file('file_pengumuman_sekolah');

            // Log file yang diterima
            Log::info('File detected.');
            Log::info('Original File Name: ' . $file->getClientOriginalName());
            Log::info('File Size: ' . $file->getSize() . ' bytes');
            Log::info('File Mime Type: ' . $file->getMimeType());

            // Periksa apakah file valid
            if ($file->isValid()) {
                Log::info('File is valid.');

                // Simpan file ke storage publik
                try {
                    $filePath = $file->store('uploads', 'public'); // Menyimpan di storage/app/public/uploads
                    Log::info('File stored successfully at: ' . $filePath);
                } catch (\Exception $e) {
                    Log::error('Error storing file: ' . $e->getMessage());
                    return response()->json(['error' => 'Failed to store the file.'], 500);
                }
            } else {
                Log::error('Uploaded file is not valid.');
                return response()->json(['error' => 'Uploaded file is not valid.'], 400);
            }
        } else {
            Log::warning('No file uploaded in the request.');
            $filePath = null; // Pastikan ini didefinisikan jika tidak ada file
        }

        // Subjek dan isi email
        $subject = $validated['judul_pengumuman_sekolah'];
        $body = $validated['isi_pengumuman'];

        // Ambil semua email dari tabel user
        $userEmails = User::pluck('email')->toArray();
        $muridEmails = Murid::pluck('email_murid')->toArray();
        $ortuEmails = Murid::pluck('email_ortu')->toArray();

        // Gabungkan semua email
        $allEmails = array_merge($userEmails, $muridEmails, $ortuEmails);

        // Debug jumlah email yang ditemukan
        Log::info('Total unique email addresses found: ' . count(array_unique($allEmails)));

        // Kirim email ke semua alamat
        foreach (array_unique($allEmails) as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::info("Preparing to send email to: $email");

                if ($filePath) {
                    // Path lengkap untuk file
                    $fullFilePath = public_path('storage/' . $filePath);
                    Log::info("Attachment path: $fullFilePath");

                    // Kirim email dengan lampiran
                    if ($this->mailService->sendEmailWithAttachment($email, $subject, $body, $fullFilePath)) {
                        Log::info("Email sent successfully to: $email");
                    } else {
                        Log::error("Failed to send email to: $email");
                    }
                } else {
                    // Kirim email tanpa lampiran
                    if ($this->mailService->sendEmail($email, $subject, $body)) {
                        Log::info("Email sent successfully to: $email");
                    } else {
                        Log::error("Failed to send email to: $email");
                    }
                }
            } else {
                Log::warning("Invalid email address: $email");
            }
        }

        return response()->json(['message' => 'Emails sent successfully!']);
    }

    public function sendWhatsapp(Request $request)
{
    // Ambil data dari request
    $judul = $request->input('judul_pengumuman_sekolah');
    $isi = $request->input('isi_pengumuman');

    // Query untuk mengambil nomor dari tabel users dan murid
    $usersNumbers = DB::table('user')->pluck('no_hp')->toArray();
    $muridNumbers = DB::table('murid')
        ->select('nohp_murid', 'nohp_ortu')
        ->get()
        ->flatMap(function ($row) {
            return [$row->nohp_murid, $row->nohp_ortu];
        })
        ->toArray();

    // Gabungkan semua nomor dan filter nomor yang valid
    $allNumbers = array_merge($usersNumbers, $muridNumbers);
    $whatsappNumbers = array_filter(array_map([$this, 'formatWhatsappNumber'], $allNumbers));

    // Loop untuk mengirim pesan ke setiap nomor
    foreach ($whatsappNumbers as $number) {
        // Persiapkan pesan
        $message = "Judul: {$judul}\nIsi Pengumuman: {$isi}";
        $params = [
            'token' => '9njb7wu00cb8woas',
            'to' => $number, // Gunakan nomor yang sudah diformat
            'body' => $message,
            'priority' => '1',
            'referenceId' => '',
            'msgId' => '',
            'mentions' => ''
        ];

        // Eksekusi cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.ultramsg.com/instance103566/messages/chat",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                "content-type: application/x-www-form-urlencoded"
            ],
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        // Log hasil
        if ($err) {
            Log::error("cURL Error #: " . $err);
        } else {
            Log::info("Respon dari API: " . $response);
        }
        Log::info("Nomor yang dikirim ke WhatsApp API: " . $number);
    }

    return response()->json(['status' => 'success']);
}

// Fungsi untuk memformat nomor ke format internasional
private function formatWhatsappNumber($number) {
    // Hapus karakter non-digit
    $number = preg_replace('/\D/', '', $number);

    // Tambahkan kode negara jika hilang
    if (substr($number, 0, 1) === '0') {
        $number = '62' . substr($number, 1); // Ganti 0 di awal dengan 62
    }

    // Tambahkan suffix untuk WhatsApp API
    return $number . '@c.us';
}




}
