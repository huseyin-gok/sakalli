# GitHub’a yükleme (adım adım)

Bu rehber, Sakallı projesini GitHub’a ilk kez yüklemek içindir.

## Ön koşul

- [Git](https://git-scm.com/download/win) kurulu
- [GitHub](https://github.com) hesabı
- Bu klasörde ilk commit yapılmış olmalı (`git log` ile kontrol)

## 1. GitHub’da boş depo oluştur

1. https://github.com/new adresine gidin
2. **Repository name:** `sakalli` (veya istediğiniz ad)
3. **Public** veya **Private** seçin
4. **Initialize this repository with** kutularını **işaretlemeyin** (README, .gitignore, license eklemeyin — zaten yerelde var)
5. **Create repository** tıklayın

## 2. Uzak depoyu bağla ve gönder

PowerShell veya Git Bash’te proje klasöründe:

```powershell
cd c:\wamp64\www\sakalli

# KULLANICI_ADINIZ ve REPO_ADI kısımlarını değiştirin
git remote add origin https://github.com/KULLANICI_ADINIZ/sakalli.git

git push -u origin main
```

SSH kullanıyorsanız:

```powershell
git remote add origin git@github.com:KULLANICI_ADINIZ/sakalli.git
git push -u origin main
```

## 3. Giriş

İlk `git push` sırasında:

- **HTTPS:** GitHub kullanıcı adı + [Personal Access Token](https://github.com/settings/tokens) (parola yerine token)
- **SSH:** Önce `ssh-keygen` ve GitHub → Settings → SSH keys’e public key ekleyin

## 4. Doğrulama

- `.env` dosyası **commit edilmemeli** (`git status` temiz, `.env` listede yok)
- `storage/secrets/` içeriği repoda olmamalı
- GitHub sayfasında `README.md` görünmeli

## Sonraki güncellemeler

```powershell
git add -A
git status
git commit -m "Degisiklik aciklamasi"
git push
```

## Sorun giderme

| Hata | Çözüm |
|------|--------|
| `remote origin already exists` | `git remote set-url origin https://github.com/...` |
| `failed to push` / auth | Token veya SSH key kontrolü |
| `rejected (fetch first)` | Uzakta README oluşturduysanız: `git pull origin main --rebase` sonra `git push` |
