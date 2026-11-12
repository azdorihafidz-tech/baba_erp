// BABA Printer Agent — program kecil di PC kasir Windows supaya ERP BABA PARFUME bisa
// mencetak struk langsung ke printer (Bluetooth lewat port COM, atau printer USB yang
// terpasang di Windows) TANPA memilih printer di browser setiap transaksi.
//
// Cara kerja: agen membuka server kecil HANYA di 127.0.0.1:17331 (tidak bisa diakses
// komputer lain). Halaman ERP mengirim data struk ESC/POS (byte yang sama persis dengan
// jalur Bluetooth browser) ke agen, agen meneruskannya ke printer.
//
// Build: lihat README.md (go build -ldflags "-H=windowsgui -s -w").
package main

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"log"
	"net"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"sync"
	"time"
)

const (
	versi    = "1.0.0"
	port     = 17331
	namaExe  = "baba-printer-agent.exe"
	maksData = 2 << 20 // 2 MB per cetak, jauh di atas ukuran struk
)

var defaultOrigins = []string{
	"https://erp.babaparfume.id",
	"http://127.0.0.1:8003",
	"http://localhost:8003",
}

type Config struct {
	Tipe    string   `json:"tipe"`    // "com" (Bluetooth/serial) | "windows" (printer USB terpasang) | ""
	Com     string   `json:"com"`     // mis. "COM5"
	Baud    int      `json:"baud"`    // default 9600 (Bluetooth mengabaikannya)
	Printer string   `json:"printer"` // nama printer Windows
	Origins []string `json:"origins"` // alamat ERP yang boleh mencetak
}

var (
	dirData   string
	cfgMu     sync.Mutex
	cfg       Config
	cetakMu   sync.Mutex // satu pekerjaan cetak dalam satu waktu
	selfHosts = map[string]bool{fmt.Sprintf("127.0.0.1:%d", port): true, fmt.Sprintf("localhost:%d", port): true}
	selfOrig  = map[string]bool{fmt.Sprintf("http://127.0.0.1:%d", port): true, fmt.Sprintf("http://localhost:%d", port): true}
	keluar    = make(chan struct{})
)

func main() {
	latar := flag.Bool("latar", false, "jalan di latar tanpa membuka halaman pengaturan (dipakai saat Windows menyala)")
	hapus := flag.Bool("hapus", false, "hentikan agen dan hapus dari startup Windows")
	flag.Parse()

	base, _ := os.UserCacheDir() // %LOCALAPPDATA%
	dirData = filepath.Join(base, "BABAPrinterAgent")
	os.MkdirAll(dirData, 0o755)
	siapkanLog()

	exe, _ := os.Executable()
	exeTerpasang := filepath.Join(dirData, namaExe)

	if *hapus {
		hentikanAgenLama()
		setAutostart(false, "")
		pesan("BABA Printer Agent", "BABA Printer Agent sudah dihentikan dan tidak akan jalan otomatis lagi.")
		return
	}

	// Dijalankan dari file unduhan → pasang/perbarui ke %LOCALAPPDATA%\BABAPrinterAgent
	if !strings.EqualFold(filepath.Clean(exe), filepath.Clean(exeTerpasang)) {
		if err := pasang(exe, exeTerpasang); err != nil {
			log.Printf("pasang gagal: %v", err)
			pesan("BABA Printer Agent", "Gagal memasang agen: "+err.Error())
			return
		}
		return
	}

	muatConfig()
	ln, err := net.Listen("tcp", fmt.Sprintf("127.0.0.1:%d", port))
	if err != nil {
		// Sudah ada agen yang jalan → cukup buka halaman pengaturannya
		if !*latar {
			bukaBrowser()
		}
		return
	}
	log.Printf("BABA Printer Agent %s jalan di 127.0.0.1:%d", versi, port)
	if !*latar {
		go func() { time.Sleep(400 * time.Millisecond); bukaBrowser() }()
	}

	srv := &http.Server{Handler: rute(), ReadHeaderTimeout: 10 * time.Second}
	go func() { <-keluar; time.Sleep(200 * time.Millisecond); srv.Close() }()
	if err := srv.Serve(ln); err != nil && !errors.Is(err, http.ErrServerClosed) {
		log.Printf("server berhenti: %v", err)
	}
}

func siapkanLog() {
	p := filepath.Join(dirData, "agent.log")
	if st, err := os.Stat(p); err == nil && st.Size() > 1<<20 {
		os.Remove(p)
	}
	if f, err := os.OpenFile(p, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o644); err == nil {
		log.SetOutput(f)
	}
}

// pasang menyalin exe ke folder agen (menghentikan versi lama kalau sedang jalan),
// lalu menjalankan salinannya. Dengan begitu file unduhan boleh dihapus.
func pasang(dari, ke string) error {
	hentikanAgenLama()
	var err error
	for i := 0; i < 10; i++ { // exe lama mungkin masih terkunci sesaat setelah berhenti
		if err = salin(dari, ke); err == nil {
			break
		}
		time.Sleep(500 * time.Millisecond)
	}
	if err != nil {
		return err
	}
	if err := setAutostart(true, `"`+ke+`" --latar`); err != nil {
		log.Printf("autostart gagal: %v", err)
	}
	return exec.Command(ke).Start()
}

func salin(dari, ke string) error {
	src, err := os.Open(dari)
	if err != nil {
		return err
	}
	defer src.Close()
	tmp := ke + ".baru"
	dst, err := os.Create(tmp)
	if err != nil {
		return err
	}
	if _, err = io.Copy(dst, src); err != nil {
		dst.Close()
		return err
	}
	dst.Close()
	return os.Rename(tmp, ke)
}

func hentikanAgenLama() {
	c := &http.Client{Timeout: 2 * time.Second}
	resp, err := c.Post(fmt.Sprintf("http://127.0.0.1:%d/api/keluar", port), "application/json", nil)
	if err != nil {
		return
	}
	resp.Body.Close()
	for i := 0; i < 20; i++ { // tunggu port bebas
		if ln, err := net.Listen("tcp", fmt.Sprintf("127.0.0.1:%d", port)); err == nil {
			ln.Close()
			time.Sleep(300 * time.Millisecond)
			return
		}
		time.Sleep(200 * time.Millisecond)
	}
}

func bukaBrowser() {
	exec.Command("rundll32", "url.dll,FileProtocolHandler", fmt.Sprintf("http://127.0.0.1:%d/", port)).Start()
}

// ---------------- Konfigurasi ----------------

func pathConfig() string { return filepath.Join(dirData, "config.json") }

func muatConfig() {
	cfgMu.Lock()
	defer cfgMu.Unlock()
	cfg = Config{Baud: 9600}
	if b, err := os.ReadFile(pathConfig()); err == nil {
		json.Unmarshal(b, &cfg)
	}
	if len(cfg.Origins) == 0 {
		cfg.Origins = append([]string{}, defaultOrigins...)
	}
}

func ambilConfig() Config {
	cfgMu.Lock()
	defer cfgMu.Unlock()
	c := cfg
	c.Origins = append([]string{}, cfg.Origins...)
	return c
}

func simpanConfig(c Config) error {
	c.Tipe = strings.TrimSpace(c.Tipe)
	c.Com = strings.ToUpper(strings.TrimSpace(c.Com))
	if c.Baud <= 0 {
		c.Baud = 9600
	}
	bersih := []string{}
	for _, o := range c.Origins {
		if o = normalOrigin(o); o != "" {
			bersih = append(bersih, o)
		}
	}
	if len(bersih) == 0 {
		bersih = append(bersih, defaultOrigins...)
	}
	c.Origins = bersih
	b, _ := json.MarshalIndent(c, "", "  ")
	if err := os.WriteFile(pathConfig(), b, 0o644); err != nil {
		return err
	}
	cfgMu.Lock()
	cfg = c
	cfgMu.Unlock()
	return nil
}

func normalOrigin(o string) string {
	return strings.ToLower(strings.TrimRight(strings.TrimSpace(o), "/"))
}

func originDiizinkan(o string) bool {
	o = normalOrigin(o)
	for _, x := range ambilConfig().Origins {
		if normalOrigin(x) == o {
			return true
		}
	}
	return false
}

func labelPrinter(c Config) string {
	switch c.Tipe {
	case "com":
		return c.Com
	case "windows":
		return c.Printer
	}
	return ""
}

func siap(c Config) bool {
	return (c.Tipe == "com" && c.Com != "") || (c.Tipe == "windows" && c.Printer != "")
}

// ---------------- Cetak ----------------

func kirim(c Config, data []byte) error {
	if len(data) == 0 {
		return errors.New("data cetak kosong")
	}
	cetakMu.Lock()
	defer cetakMu.Unlock()
	switch c.Tipe {
	case "com":
		return cetakCom(c.Com, c.Baud, data)
	case "windows":
		return cetakWindows(c.Printer, data)
	}
	return fmt.Errorf("printer belum diatur — buka http://127.0.0.1:%d di PC ini", port)
}

var bukaLaciBytes = []byte{0x1B, 0x70, 0x00, 0x19, 0xFA}

func dataTesCetak(c Config) []byte {
	var b bytes.Buffer
	b.Write([]byte{0x1B, 0x40, 0x1B, 0x61, 0x01}) // init, rata tengah
	b.Write([]byte{0x1B, 0x45, 0x01})
	b.WriteString("BABA PRINTER AGENT\n")
	b.Write([]byte{0x1B, 0x45, 0x00})
	b.WriteString("Tes cetak berhasil\n")
	b.WriteString("--------------------------------\n")
	b.Write([]byte{0x1B, 0x61, 0x00})
	b.WriteString("Printer : " + labelPrinter(c) + "\n")
	b.WriteString("Waktu   : " + time.Now().Format("02/01/2006 15:04:05") + "\n")
	b.WriteString("Versi   : " + versi + "\n\n\n\n")
	return b.Bytes()
}

// ---------------- HTTP ----------------

type jawab map[string]any

func tulisJSON(w http.ResponseWriter, kode int, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.Header().Set("Cache-Control", "no-store")
	w.WriteHeader(kode)
	json.NewEncoder(w).Encode(v)
}

// jaga: tolak Host asing (cegah DNS rebinding). Untuk endpoint ERP (bolehERP),
// halaman dari alamat ERP yang terdaftar boleh memanggil (CORS); endpoint
// pengaturan hanya untuk halaman agen sendiri.
func jaga(bolehERP bool, h http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !selfHosts[strings.ToLower(r.Host)] {
			tulisJSON(w, http.StatusForbidden, jawab{"ok": false, "pesan": "host tidak dikenal"})
			return
		}
		origin := r.Header.Get("Origin")
		if origin != "" && !selfOrig[normalOrigin(origin)] {
			if !bolehERP || !originDiizinkan(origin) {
				tulisJSON(w, http.StatusForbidden, jawab{"ok": false, "pesan": "alamat " + origin + " belum diizinkan di pengaturan BABA Printer Agent"})
				return
			}
			w.Header().Set("Access-Control-Allow-Origin", origin)
			w.Header().Set("Vary", "Origin")
			w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
			w.Header().Set("Access-Control-Allow-Headers", "Content-Type")
			w.Header().Set("Access-Control-Max-Age", "600")
			if r.Header.Get("Access-Control-Request-Private-Network") != "" {
				w.Header().Set("Access-Control-Allow-Private-Network", "true")
			}
		}
		if r.Method == http.MethodOptions {
			w.WriteHeader(http.StatusNoContent)
			return
		}
		h(w, r)
	}
}

func hanyaPost(h http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			tulisJSON(w, http.StatusMethodNotAllowed, jawab{"ok": false, "pesan": "gunakan POST"})
			return
		}
		h(w, r)
	}
}

func rute() *http.ServeMux {
	m := http.NewServeMux()

	m.HandleFunc("/status", jaga(true, func(w http.ResponseWriter, r *http.Request) {
		c := ambilConfig()
		tulisJSON(w, 200, jawab{"agent": "baba-printer-agent", "versi": versi, "siap": siap(c), "tipe": c.Tipe, "printer": labelPrinter(c)})
	}))

	m.HandleFunc("/print", jaga(true, hanyaPost(func(w http.ResponseWriter, r *http.Request) {
		var in struct {
			Data string `json:"data"`
		}
		if err := json.NewDecoder(io.LimitReader(r.Body, maksData*2)).Decode(&in); err != nil {
			tulisJSON(w, 400, jawab{"ok": false, "pesan": "format permintaan salah"})
			return
		}
		data, err := base64.StdEncoding.DecodeString(in.Data)
		if err != nil || len(data) > maksData {
			tulisJSON(w, 400, jawab{"ok": false, "pesan": "data cetak tidak valid"})
			return
		}
		c := ambilConfig()
		if err := kirim(c, data); err != nil {
			log.Printf("cetak gagal (%s): %v", labelPrinter(c), err)
			tulisJSON(w, 500, jawab{"ok": false, "pesan": err.Error()})
			return
		}
		log.Printf("cetak %d byte ke %s (asal %s)", len(data), labelPrinter(c), r.Header.Get("Origin"))
		tulisJSON(w, 200, jawab{"ok": true, "pesan": "Tercetak di " + labelPrinter(c)})
	})))

	m.HandleFunc("/drawer", jaga(true, hanyaPost(func(w http.ResponseWriter, r *http.Request) {
		c := ambilConfig()
		if err := kirim(c, bukaLaciBytes); err != nil {
			tulisJSON(w, 500, jawab{"ok": false, "pesan": err.Error()})
			return
		}
		log.Printf("buka laci lewat %s", labelPrinter(c))
		tulisJSON(w, 200, jawab{"ok": true, "pesan": "Laci dibuka"})
	})))

	// ---- Khusus halaman pengaturan agen sendiri ----
	m.HandleFunc("/", jaga(false, func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/" {
			http.NotFound(w, r)
			return
		}
		w.Header().Set("Content-Type", "text/html; charset=utf-8")
		w.Header().Set("Cache-Control", "no-store")
		w.Header().Set("X-Frame-Options", "DENY")
		io.WriteString(w, halamanPengaturan)
	}))

	m.HandleFunc("/api/perangkat", jaga(false, func(w http.ResponseWriter, r *http.Request) {
		tulisJSON(w, 200, jawab{"com": daftarCom(), "windows": daftarPrinterWindows()})
	}))

	m.HandleFunc("/api/config", jaga(false, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodPost {
			var c Config
			if err := json.NewDecoder(io.LimitReader(r.Body, 64<<10)).Decode(&c); err != nil {
				tulisJSON(w, 400, jawab{"ok": false, "pesan": "format salah"})
				return
			}
			if c.Tipe != "" && c.Tipe != "com" && c.Tipe != "windows" {
				tulisJSON(w, 400, jawab{"ok": false, "pesan": "tipe printer tidak dikenal"})
				return
			}
			if err := simpanConfig(c); err != nil {
				tulisJSON(w, 500, jawab{"ok": false, "pesan": err.Error()})
				return
			}
			log.Printf("pengaturan disimpan: %s %s", c.Tipe, labelPrinter(c))
		}
		c := ambilConfig()
		tulisJSON(w, 200, jawab{"ok": true, "config": c, "siap": siap(c), "versi": versi, "autostart": autostartAktif()})
	}))

	m.HandleFunc("/api/tes", jaga(false, hanyaPost(func(w http.ResponseWriter, r *http.Request) {
		c := ambilConfig()
		data := dataTesCetak(c)
		if r.URL.Query().Get("laci") == "1" {
			data = bukaLaciBytes
		}
		if err := kirim(c, data); err != nil {
			tulisJSON(w, 500, jawab{"ok": false, "pesan": err.Error()})
			return
		}
		tulisJSON(w, 200, jawab{"ok": true, "pesan": "Terkirim ke " + labelPrinter(c)})
	})))

	m.HandleFunc("/api/autostart", jaga(false, hanyaPost(func(w http.ResponseWriter, r *http.Request) {
		var in struct {
			Aktif bool `json:"aktif"`
		}
		json.NewDecoder(io.LimitReader(r.Body, 1024)).Decode(&in)
		exe := filepath.Join(dirData, namaExe)
		if err := setAutostart(in.Aktif, `"`+exe+`" --latar`); err != nil {
			tulisJSON(w, 500, jawab{"ok": false, "pesan": err.Error()})
			return
		}
		tulisJSON(w, 200, jawab{"ok": true, "autostart": autostartAktif()})
	})))

	m.HandleFunc("/api/keluar", jaga(false, hanyaPost(func(w http.ResponseWriter, r *http.Request) {
		log.Printf("agen dihentikan")
		tulisJSON(w, 200, jawab{"ok": true})
		select {
		case <-keluar:
		default:
			close(keluar)
		}
	})))

	return m
}
