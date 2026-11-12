package main

// Panggilan Windows API langsung (hanya pustaka standar Go, tanpa dependensi luar):
// winspool (cetak RAW ke printer Windows), kernel32 (port COM), advapi32 (registry),
// user32 (kotak pesan).

import (
	"errors"
	"fmt"
	"sort"
	"strconv"
	"strings"
	"syscall"
	"time"
	"unsafe"
)

var (
	winspool       = syscall.NewLazyDLL("winspool.drv")
	pOpenPrinter   = winspool.NewProc("OpenPrinterW")
	pClosePrinter  = winspool.NewProc("ClosePrinter")
	pStartDoc      = winspool.NewProc("StartDocPrinterW")
	pEndDoc        = winspool.NewProc("EndDocPrinter")
	pStartPage     = winspool.NewProc("StartPagePrinter")
	pEndPage       = winspool.NewProc("EndPagePrinter")
	pWritePrinter  = winspool.NewProc("WritePrinter")
	pEnumPrinters  = winspool.NewProc("EnumPrintersW")
	kernel32       = syscall.NewLazyDLL("kernel32.dll")
	pGetCommState  = kernel32.NewProc("GetCommState")
	pSetCommState  = kernel32.NewProc("SetCommState")
	pSetCommTO     = kernel32.NewProc("SetCommTimeouts")
	advapi32       = syscall.NewLazyDLL("advapi32.dll")
	pRegEnumValue  = advapi32.NewProc("RegEnumValueW")
	pRegCreateKey  = advapi32.NewProc("RegCreateKeyExW")
	pRegSetValue   = advapi32.NewProc("RegSetValueExW")
	pRegDeleteVal  = advapi32.NewProc("RegDeleteValueW")
	user32         = syscall.NewLazyDLL("user32.dll")
	pMessageBox    = user32.NewProc("MessageBoxW")
)

func u16(s string) *uint16 {
	p, _ := syscall.UTF16PtrFromString(s)
	return p
}

func u16str(p *uint16) string {
	if p == nil {
		return ""
	}
	n := 0
	for ptr := unsafe.Pointer(p); *(*uint16)(ptr) != 0; ptr = unsafe.Add(ptr, 2) {
		n++
	}
	return syscall.UTF16ToString(unsafe.Slice(p, n))
}

// ---------------- Printer Windows (RAW) ----------------

type docInfo1 struct {
	DocName    *uint16
	OutputFile *uint16
	Datatype   *uint16
}

// cetakWindows mengirim byte ESC/POS apa adanya (datatype RAW) ke printer Windows,
// melewati driver grafis — sama seperti dikirim lewat Bluetooth.
func cetakWindows(nama string, data []byte) error {
	if nama == "" {
		return errors.New("printer Windows belum dipilih")
	}
	var h syscall.Handle
	if r, _, e := pOpenPrinter.Call(uintptr(unsafe.Pointer(u16(nama))), uintptr(unsafe.Pointer(&h)), 0); r == 0 {
		return fmt.Errorf("printer %q tidak ditemukan (%v)", nama, e)
	}
	defer pClosePrinter.Call(uintptr(h))

	doc := docInfo1{DocName: u16("Struk BABA PARFUME"), Datatype: u16("RAW")}
	if r, _, e := pStartDoc.Call(uintptr(h), 1, uintptr(unsafe.Pointer(&doc))); r == 0 {
		return fmt.Errorf("gagal memulai cetak (%v)", e)
	}
	defer pEndDoc.Call(uintptr(h))
	if r, _, e := pStartPage.Call(uintptr(h)); r == 0 {
		return fmt.Errorf("gagal memulai halaman (%v)", e)
	}
	defer pEndPage.Call(uintptr(h))

	var tertulis uint32
	r, _, e := pWritePrinter.Call(uintptr(h), uintptr(unsafe.Pointer(&data[0])), uintptr(len(data)), uintptr(unsafe.Pointer(&tertulis)))
	if r == 0 || int(tertulis) != len(data) {
		return fmt.Errorf("gagal mengirim data ke printer (%v)", e)
	}
	return nil
}

type printerInfo4 struct {
	Name       *uint16
	Server     *uint16
	Attributes uint32
}

func daftarPrinterWindows() []string {
	const flags = 2 | 4 // PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS
	var perlu, jumlah uint32
	pEnumPrinters.Call(flags, 0, 4, 0, 0, uintptr(unsafe.Pointer(&perlu)), uintptr(unsafe.Pointer(&jumlah)))
	if perlu == 0 {
		return []string{}
	}
	buf := make([]byte, perlu)
	if r, _, _ := pEnumPrinters.Call(flags, 0, 4, uintptr(unsafe.Pointer(&buf[0])), uintptr(perlu),
		uintptr(unsafe.Pointer(&perlu)), uintptr(unsafe.Pointer(&jumlah))); r == 0 || jumlah == 0 {
		return []string{}
	}
	info := unsafe.Slice((*printerInfo4)(unsafe.Pointer(&buf[0])), jumlah)
	hasil := make([]string, 0, jumlah)
	for _, p := range info {
		hasil = append(hasil, u16str(p.Name))
	}
	sort.Strings(hasil)
	return hasil
}

// ---------------- Port COM (Bluetooth SPP / USB-serial) ----------------

type dcb struct {
	DCBlength  uint32
	BaudRate   uint32
	Flags      uint32
	wReserved  uint16
	XonLim     uint16
	XoffLim    uint16
	ByteSize   byte
	Parity     byte
	StopBits   byte
	XonChar    byte
	XoffChar   byte
	ErrorChar  byte
	EofChar    byte
	EvtChar    byte
	wReserved1 uint16
}

type commTimeouts struct {
	ReadIntervalTimeout         uint32
	ReadTotalTimeoutMultiplier  uint32
	ReadTotalTimeoutConstant    uint32
	WriteTotalTimeoutMultiplier uint32
	WriteTotalTimeoutConstant   uint32
}

func bukaCom(com string) (syscall.Handle, error) {
	path := `\\.\` + strings.ToUpper(strings.TrimSpace(com))
	return syscall.CreateFile(u16(path), syscall.GENERIC_READ|syscall.GENERIC_WRITE, 0, nil, syscall.OPEN_EXISTING, 0, 0)
}

// cetakCom membuka port COM (untuk Bluetooth, membuka port = menyambung ke printer),
// mengirim data per potongan kecil (buffer printer thermal kecil), lalu menutup port
// supaya printer bebas dipakai perangkat lain.
func cetakCom(com string, baud int, data []byte) error {
	if strings.TrimSpace(com) == "" {
		return errors.New("port COM belum dipilih")
	}
	h, err := bukaCom(com)
	if err != nil {
		// Bluetooth kadang perlu waktu menyambung — coba sekali lagi
		time.Sleep(1500 * time.Millisecond)
		if h, err = bukaCom(com); err != nil {
			return fmt.Errorf("%s tidak bisa dibuka (%v). Pastikan printer menyala, sudah di-pairing, dan tidak sedang dipakai perangkat lain", com, err)
		}
	}
	defer syscall.CloseHandle(h)

	if baud <= 0 {
		baud = 9600
	}
	var d dcb
	d.DCBlength = uint32(unsafe.Sizeof(d))
	if r, _, _ := pGetCommState.Call(uintptr(h), uintptr(unsafe.Pointer(&d))); r != 0 {
		d.BaudRate, d.ByteSize, d.Parity, d.StopBits = uint32(baud), 8, 0, 0
		d.Flags = 1 // fBinary, tanpa flow control
		pSetCommState.Call(uintptr(h), uintptr(unsafe.Pointer(&d)))
	}
	to := commTimeouts{WriteTotalTimeoutMultiplier: 2, WriteTotalTimeoutConstant: 15000}
	pSetCommTO.Call(uintptr(h), uintptr(unsafe.Pointer(&to)))

	const potongan = 512
	for awal := 0; awal < len(data); {
		akhir := min(awal+potongan, len(data))
		var n uint32
		if err := syscall.WriteFile(h, data[awal:akhir], &n, nil); err != nil {
			return fmt.Errorf("gagal mengirim ke %s (%v)", com, err)
		}
		if n == 0 {
			return fmt.Errorf("printer di %s tidak merespons (timeout)", com)
		}
		awal += int(n)
		time.Sleep(15 * time.Millisecond)
	}
	time.Sleep(300 * time.Millisecond) // beri waktu data terkirim sebelum port ditutup
	return nil
}

type portCom struct {
	Port       string `json:"port"`
	Keterangan string `json:"keterangan"`
}

func daftarCom() []portCom {
	hasil := []portCom{}
	var k syscall.Handle
	if syscall.RegOpenKeyEx(syscall.HKEY_LOCAL_MACHINE, u16(`HARDWARE\DEVICEMAP\SERIALCOMM`), 0, syscall.KEY_READ, &k) != nil {
		return hasil
	}
	defer syscall.RegCloseKey(k)
	for i := 0; i < 256; i++ {
		nama := make([]uint16, 256)
		nlen := uint32(len(nama))
		isi := make([]uint16, 64)
		ilen := uint32(len(isi) * 2)
		var tipe uint32
		r, _, _ := pRegEnumValue.Call(uintptr(k), uintptr(i), uintptr(unsafe.Pointer(&nama[0])), uintptr(unsafe.Pointer(&nlen)),
			0, uintptr(unsafe.Pointer(&tipe)), uintptr(unsafe.Pointer(&isi[0])), uintptr(unsafe.Pointer(&ilen)))
		if r != 0 {
			break
		}
		dev := strings.ToLower(syscall.UTF16ToString(nama[:nlen]))
		ket := "Serial"
		switch {
		case strings.Contains(dev, "bth"):
			ket = "Bluetooth"
		case strings.Contains(dev, "usb") || strings.Contains(dev, "vcp") || strings.Contains(dev, "ser"):
			ket = "USB / Serial"
		}
		hasil = append(hasil, portCom{Port: syscall.UTF16ToString(isi), Keterangan: ket})
	}
	sort.Slice(hasil, func(a, b int) bool { return nomorCom(hasil[a].Port) < nomorCom(hasil[b].Port) })
	return hasil
}

func nomorCom(s string) int {
	n, _ := strconv.Atoi(strings.TrimPrefix(strings.ToUpper(s), "COM"))
	return n
}

// ---------------- Registry: jalan otomatis saat Windows menyala ----------------

const runKey = `Software\Microsoft\Windows\CurrentVersion\Run`
const runNama = "BABAPrinterAgent"

func bukaRunKey() (syscall.Handle, error) {
	var k syscall.Handle
	const keyAll = 0xF003F
	if r, _, _ := pRegCreateKey.Call(uintptr(syscall.HKEY_CURRENT_USER), uintptr(unsafe.Pointer(u16(runKey))), 0, 0, 0,
		keyAll, 0, uintptr(unsafe.Pointer(&k)), 0); r != 0 {
		return 0, fmt.Errorf("registry error %d", r)
	}
	return k, nil
}

func setAutostart(aktif bool, perintah string) error {
	k, err := bukaRunKey()
	if err != nil {
		return err
	}
	defer syscall.RegCloseKey(k)
	if !aktif {
		pRegDeleteVal.Call(uintptr(k), uintptr(unsafe.Pointer(u16(runNama))))
		return nil
	}
	v, _ := syscall.UTF16FromString(perintah)
	if r, _, _ := pRegSetValue.Call(uintptr(k), uintptr(unsafe.Pointer(u16(runNama))), 0, syscall.REG_SZ,
		uintptr(unsafe.Pointer(&v[0])), uintptr(len(v)*2)); r != 0 {
		return fmt.Errorf("registry error %d", r)
	}
	return nil
}

func autostartAktif() bool {
	k, err := bukaRunKey()
	if err != nil {
		return false
	}
	defer syscall.RegCloseKey(k)
	var tipe, ukuran uint32
	return syscall.RegQueryValueEx(k, u16(runNama), nil, &tipe, nil, &ukuran) == nil && ukuran > 0
}

func pesan(judul, isi string) {
	pMessageBox.Call(0, uintptr(unsafe.Pointer(u16(isi))), uintptr(unsafe.Pointer(u16(judul))), 0x40) // MB_ICONINFORMATION
}
