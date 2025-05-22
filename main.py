    #!/usr/bin/env python3
    import serial
    import requests
    import time

    # ─── CONFIG ───────────────────────────────────────────────────────────────
    SERIAL_PORT = 'COM4'                          # your Windows Arduino port
    BAUDRATE    = 9600
    API_URL     = 'http://localhost/newpull/rfid.php'  # adjust if your Laravel host/port differ
    # ──────────────────────────────────────────────────────────────────────────

    def main():
        try:
            ser = serial.Serial(SERIAL_PORT, BAUDRATE, timeout=1)
            print(f"[+] Listening on {SERIAL_PORT} at {BAUDRATE} baud")
        except Exception as e:
            print(f"[!] Could not open {SERIAL_PORT}: {e}")
            return

        while True:
            try:
                raw = ser.readline().decode('utf-8', errors='ignore').strip()
                if not raw:
                    continue
                uid = raw.upper()
                resp = requests.post(API_URL, json={'uid': uid}, timeout=2)
                if resp.status_code in (200, 201):
                    print(f"[+] Stored UID {uid}")
                else:
                    print(f"[!] API error {resp.status_code}: {resp.text}")
            except Exception as e:
                print(f"[!] Exception: {e}")
            time.sleep(0.1)

    if __name__ == '__main__':
        main()