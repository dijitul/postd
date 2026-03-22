# postd.uk - Git Setup & First Push Script
# Run this in PowerShell from any directory
# Requirements: Git installed, GitHub CLI (gh) installed OR create the repo manually first

$projectPath = "D:\Git\postd.uk"
$repoName = "postd"
$githubUser = "dijitul"  # Change this to your GitHub username or org

Write-Host "=== postd.uk Git Setup ===" -ForegroundColor Cyan

# 1. Init git repo
Write-Host "`n[1/6] Initialising git repository..." -ForegroundColor Yellow
cd $projectPath
git init
git checkout -b main

# 2. Add .gitignore is already there, stage everything
Write-Host "`n[2/6] Staging all files..." -ForegroundColor Yellow
git add .
git status

# 3. First commit
Write-Host "`n[3/6] Creating initial commit..." -ForegroundColor Yellow
$commitMsg = @"
Initial commit - postd.uk scaffold

Full project scaffold including:
* CLAUDE.md master specification
* Laravel 11 API backend structure (api/)
* React + Vite + Tailwind PWA (web/)
* Platform content rules and AI prompt templates (docs/)
* GitHub Actions CI/CD pipeline (.github/workflows/)
* Server provision script (scripts/provision.sh)
* Nginx and Supervisor configs (scripts/)
"@
git commit -m $commitMsg

# 4. Create GitHub repo (requires GitHub CLI - gh)
Write-Host "`n[4/6] Creating GitHub repository '$repoName'..." -ForegroundColor Yellow
Write-Host "Attempting with GitHub CLI..." -ForegroundColor Gray

$ghAvailable = Get-Command gh -ErrorAction SilentlyContinue
if ($ghAvailable) {
    gh repo create "$githubUser/$repoName" --private --source=. --remote=origin --push
    Write-Host "Repository created and pushed!" -ForegroundColor Green
} else {
    Write-Host "GitHub CLI not found. Do this manually:" -ForegroundColor Red
    Write-Host "  1. Go to https://github.com/new" -ForegroundColor White
    Write-Host "  2. Create repo named: $repoName" -ForegroundColor White
    Write-Host "  3. Set to Private" -ForegroundColor White
    Write-Host "  4. DO NOT initialise with README" -ForegroundColor White
    Write-Host "  5. Then run these commands:" -ForegroundColor White
    Write-Host ""
    Write-Host "     git remote add origin git@github.com:$githubUser/$repoName.git" -ForegroundColor Cyan
    Write-Host "     git push -u origin main" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Press Enter once you've done this to continue..." -ForegroundColor Yellow
    Read-Host
}

# 5. Remind about GitHub Secrets needed for CI/CD
Write-Host "`n[5/6] GitHub Actions Secrets needed" -ForegroundColor Yellow
Write-Host "Go to: https://github.com/$githubUser/$repoName/settings/secrets/actions" -ForegroundColor White
Write-Host ""
Write-Host "Add these secrets:" -ForegroundColor Cyan
Write-Host "  SERVER_HOST    = 144.126.207.135" -ForegroundColor White
Write-Host "  SERVER_USER    = postduk" -ForegroundColor White
Write-Host "  SERVER_PATH    = /var/www/postd" -ForegroundColor White
Write-Host "  SERVER_SSH_KEY = [paste the PRIVATE key from the server after running provision.sh]" -ForegroundColor White
Write-Host ""
Write-Host "The provision script generates the SSH key. After running it on the server:" -ForegroundColor Gray
Write-Host "  cat /home/postduk/.ssh/deploy_key       <- this is the PRIVATE key (for GitHub Secret)" -ForegroundColor Gray
Write-Host "  cat /home/postduk/.ssh/deploy_key.pub   <- this is the PUBLIC key (add to GitHub Deploy Keys)" -ForegroundColor Gray

# 6. Done
Write-Host "`n[6/6] Done!" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "  1. SSH into server: ssh root@144.126.207.135" -ForegroundColor White
Write-Host "  2. Upload provision script: scp D:\Git\postd.uk\scripts\provision.sh root@144.126.207.135:/tmp/" -ForegroundColor White
Write-Host "  3. Run it: POSTD_DB_PASSWORD='choose-a-strong-password' bash /tmp/provision.sh" -ForegroundColor White
Write-Host "  4. Copy the deploy key output into GitHub Secrets (SERVER_SSH_KEY)" -ForegroundColor White
Write-Host "  5. Add the .env file to the server: scp .env root@144.126.207.135:/var/www/postd/api/.env" -ForegroundColor White
Write-Host "  6. Push any change to main branch -> auto-deploys to postd.uk" -ForegroundColor White
Write-Host ""
Write-Host "=== postd.uk is ready to fly! ===" -ForegroundColor Cyan
