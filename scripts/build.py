from pathlib import Path
import zipfile
root = Path(__file__).resolve().parent.parent
with zipfile.ZipFile(root / 'fontready-wordpress.zip', 'w') as archive:
    for path in sorted((root / 'fontready').rglob('*')):
        if path.is_file():
            member = zipfile.ZipInfo(str(path.relative_to(root)), (2026, 1, 1, 0, 0, 0))
            member.compress_type = zipfile.ZIP_DEFLATED
            member.external_attr = 0o644 << 16
            archive.writestr(member, path.read_bytes())
print('Built fontready-wordpress.zip')
