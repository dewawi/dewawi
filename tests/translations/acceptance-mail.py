#!/usr/bin/env python3
"""Capture DE/EN MIME messages at a local sink; never relay messages to external recipients."""
from email import policy
from email.parser import BytesParser
import hashlib
import json
import os
from pathlib import Path
import socketserver
import subprocess
import sys
import threading

ROOT = Path(__file__).resolve().parents[2]


class Sink(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b'220 isolated T3 sink\r\n')
        while line := self.rfile.readline():
            command = line.upper()
            if command.startswith(b'EHLO'):
                self.wfile.write(b'250-isolated\r\n250 SIZE 10000000\r\n')
            elif command.startswith(b'DATA'):
                self.wfile.write(b'354 Continue\r\n')
                data = []
                while (line := self.rfile.readline()) not in [b'.\r\n', b'']:
                    data.append(line[1:] if line.startswith(b'..') else line)
                self.server.messages.append(b''.join(data))
                self.wfile.write(b'250 Captured locally\r\n')
            elif command.startswith(b'QUIT'):
                self.wfile.write(b'221 Bye\r\n')
                return
            else:
                self.wfile.write(b'250 OK\r\n')


def main():
    with socketserver.ThreadingTCPServer(('127.0.0.1', 0), Sink) as server:
        server.messages = []
        server.daemon_threads = True
        threading.Thread(target=server.serve_forever, daemon=True).start()
        try:
            process = subprocess.run(['php', 'tests/translations/acceptance-mail.php'], cwd=ROOT, env=dict(os.environ, DEWAWI_T3_SMTP_PORT=str(server.server_address[1])), capture_output=True, text=True, timeout=40)
        finally:
            server.shutdown()
        if process.returncode:
            print(json.dumps({'status': 'failed', 'stdout': process.stdout, 'stderr': process.stderr}))
            return 1
        evidence = json.loads(process.stdout)
        results = []
        for expected, raw in zip(evidence['results'], server.messages):
            mail = BytesParser(policy=policy.default).parsebytes(raw)
            attachments = list(mail.iter_attachments())
            body = mail.get_body(('html',)).get_content()
            checks = {'smtp_accepted': expected['smtp_accepted'], 'recipient_is_synthetic': str(mail['To']) == 'sink@example.invalid',
                      'localized_subject': str(mail['Subject']) == expected['subject'] and 't#t' not in str(mail['Subject']),
                      'localized_body_and_utf8': expected['body'] in body and 'Jörg' in body and 't#t' not in body,
                      'pdf_attachment_unchanged': len(attachments) == 1 and hashlib.sha256(attachments[0].get_payload(decode=True)).hexdigest() == expected['attachment_sha256'],
                      'attachment_name': len(attachments) == 1 and attachments[0].get_filename() == expected['attachment_name'],
                      'ui_context_preserved': expected['ui_context_preserved']}
            results.append({'locale': expected['locale'], 'checks': checks, 'subject': str(mail['Subject']), 'body': body, 'attachment_name': expected['attachment_name']})
        passed = sum(sum(row['checks'].values()) for row in results)
        failed = sum(sum(not value for value in row['checks'].values()) for row in results) + (len(server.messages) != 2)
        print(json.dumps({'scope': evidence['scope'], 'messages_captured': len(server.messages), 'results': results, 'counts': {'passed': passed, 'failed': failed}, 'stderr': process.stderr}, ensure_ascii=False, indent=2))
        return int(failed != 0)


if __name__ == '__main__':
    sys.exit(main())
