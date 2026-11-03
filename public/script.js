// Item
/** @type {HTMLCanvasElement} */
const gameScreen = document.getElementById("gameScreen")
const healthElement = document.getElementById("health")
const bulletElement = document.getElementById("bullets")
const waveElement = document.getElementById("wave")
const gameOverElement = document.getElementById("gameOver")
const ctx = gameScreen.getContext("2d")
gameScreen.height = window.innerHeight
gameScreen.width = window.innerWidth

// Data
const bulletSpeed = 6.5
const movementSpeed = 5.5

// Functions
function blankScreen() {
    ctx.fillStyle = "white"
    ctx.strokeStyle = "green"
    ctx.fillRect(0, 0, gameScreen.width, gameScreen.height)
    ctx.strokeRect(0, 0, gameScreen.width, gameScreen.height)
}

function drawRect(x, y, h, w, color = "red") {
    ctx.fillStyle = color
    ctx.fillRect(x, y, w, h)
}

function Collison(a, b) {
    return !(
        ((a[1] + a[2]) < (b[1])) ||
        (a[1] > (b[1] + b[2])) ||
        ((a[0] + a[3]) < b[0]) ||
        (a[0] > (b[0] + b[3]))
    );
}

// Global Variables
let globalCounter = 0
const keys = {}
const enemies = []
const bullets = []
const bulletCrates = []
let bulletRequest = false;

const waveDat = {
    wave: 1,
    spawnCap: 30,
    enemiesToSpawn: 10,
    enemiesPerWave: 5,
    spawnCapIncreasePerWave: 5,
    spawnChanceRandNumMax: 100,
    hardChanceWave: 50,
    currentWave: {
        spawnedEnemies: 0
    }
}

const player = {
    health: 100,
    regenHealth: 5,
    ailments: {
        grabbed: {
            inflict: false,
            duration: 0
        },
        slowed: {
            inflict: false,
            slowedBy: 1,
            duration: 0
        }
    },
    weapon: {
        ammo: 10,
        dmg: 8,
        maxEnemiesHit: 5,
        bulletCrateGives: 2,
        bullet: [0, 0, 5, 5, "black"]
    },
    data: [10, 10, 10, 10, "green"]
}

// Game Functions
function spawnEnemies() {
    if (enemies.length <= 2 || Math.round(Math.random() * waveDat.spawnChanceRandNumMax) < 2) {
        const rand = Math.floor(Math.random() * 20)

        const enemySpawn = [Math.floor(Math.random() * (gameScreen.width - 100)), Math.floor(Math.random() * (gameScreen.height - 100))]

        let enemy = null;
        if (rand === 5) {
            enemy = {data: [...enemySpawn, 10, 10, "red"], Collided: false, collisonCount: 0, health: Math.round(Math.random()*50), speed: 8.5}
        } else if (rand === 10) {
            enemy = {data: [...enemySpawn, 10, 10, "yellow"], Collided: false, collisonCount: 0, health: Math.round(Math.random()*350), speed: 3.5}
        }
        waveDat.currentWave.spawnedEnemies += 1;
        enemies.push(enemy ?? {data: [...enemySpawn, 10, 10, "orange"], Collided: false, collisonCount: 0, health: Math.round(Math.random()*100), speed: 5.5})
    }
}

function moveEnemies() {
    enemies.forEach(element => {
        if (element.data[0] > player.data[0]) {element.data[0] -= 0.5*element.speed*(Math.random()*1.5)}
        else {element.data[0] += 0.5*element.speed*(Math.random()*1.5)}

        if (element.data[1] > player.data[1]) {element.data[1] -= 0.5*element.speed*(Math.random()*1.5)}
        else {element.data[1] += 0.5*element.speed*(Math.random()*1.5)}
    });
}

function spawnBullets(enemyId) {
    bullets.push({target: enemyId, data: [player.data[0], player.data[1], player.weapon.bullet[2], player.weapon.bullet[3], player.weapon.bullet[4]], enemiesHit: 0})
}

function moveBullets() {
    for (const bullet of bullets) {
        const enemy = enemies[bullet.target];
        if (!enemy) {
            bullets.splice(bullets.indexOf(bullet), 1)
            continue;
        }

        if (bullet.data[0] > enemy.data[0]) {bullet.data[0] -= 0.5*bulletSpeed*(Math.random()*1.5)}
        else {bullet.data[0] += 0.5*bulletSpeed*(Math.random()*1.5)}

        if (bullet.data[1] > enemy.data[1]) {bullet.data[1] -= 0.5*bulletSpeed*(Math.random()*1.5)}
        else {bullet.data[1] += 0.5*bulletSpeed*(Math.random()*1.5)}
    }
}

function spawnBulletCrate() {
    if (bulletCrates.length === 0 || Math.floor(Math.random() * waveDat.spawnChanceRandNumMax * 4) === 5) {
        const x = Math.floor(Math.random() * gameScreen.width-40)
        const y = Math.floor(Math.random() * gameScreen.height-40)

        bulletCrates.push([x, y, 20, 20, "blue"])
    }
}

// Events
document.addEventListener("keydown", (event) => {
    keys[event.key] = true;
})

document.addEventListener("keyup", (event) => {
    keys[event.key] = false;
})

// GameLoop
function gameLoop() {
    globalCounter += 1;

    if (waveDat.currentWave.spawnedEnemies >= waveDat.enemiesToSpawn) {
        waveDat.wave +=1
        waveDat.currentWave.spawnedEnemies = 0
        waveDat.spawnCap += waveDat.spawnCapIncreasePerWave
        waveDat.enemiesToSpawn += waveDat.enemiesPerWave
        if (Math.floor(Math.random() * 10) === 5) {waveDat.spawnChanceRandNumMax = waveDat.hardChanceWave}
        else {waveDat.spawnChanceRandNumMax = 100 - (waveDat.wave+10)}

        if (player.health <= 90) {
            player.health += player.regenHealth
        }
    }

    for (const ailment of Object.values(player.ailments)) {
        if (ailment.inflict === true && globalCounter >= ailment.duration) {
            ailment.inflict = false
            ailment.duration = 0
        }
    }

    if (player.weapon.cooldown && globalCounter >= player.weapon.cooldownCounter) {
        player.weapon.cooldown = false
        player.weapon.cooldownCounter = 0
    }

    if (keys["e"] === true && !player.weapon.cooldown) {
        player.weapon.cooldownCounter = globalCounter + 20;
        player.weapon.cooldown = true;
        bulletRequest = true;
    }

    if (!player.ailments.grabbed.inflict) {
        let playerX = player.data[0];
        let playerY = player.data[1];

        if (keys["w"] === true) {playerY = player.data[1]-(0.5*movementSpeed)*(player.ailments.slowed.inflict ? player.ailments.slowed.slowedBy : 1)}
        if (keys["s"] === true) {playerY = player.data[1]+(0.5*movementSpeed)*(player.ailments.slowed.inflict ? player.ailments.slowed.slowedBy : 1)}
        if (keys["d"] === true) {playerX = player.data[0]+(0.5*movementSpeed)*(player.ailments.slowed.inflict ? player.ailments.slowed.slowedBy : 1)}
        if (keys["a"] === true) {playerX = player.data[0]-(0.5*movementSpeed)*(player.ailments.slowed.inflict ? player.ailments.slowed.slowedBy : 1)}

        if ((playerY <= gameScreen.height && playerY >= 0) && (playerX <= gameScreen.width && playerX >= 0)) {
            player.data[1] = playerY
            player.data[0] = playerX
        }
    }

    blankScreen()
    drawRect(...player.data)

    if (enemies.length < waveDat.spawnCap) {spawnEnemies()}
    spawnBulletCrate();
    for (const crate of bulletCrates) {
        if (Collison(crate, player.data)) {
            player.weapon.ammo += player.weapon.bulletCrateGives;
            bulletCrates.splice(bulletCrates.indexOf(crate), 1)
            continue;
        }

        drawRect(...crate)
    }

    moveEnemies();
    moveBullets();
    for (const element of enemies) {
        if (!element) {continue;}

        for (const bullet of bullets) {
            if (Collison(bullet.data, element.data)) {
                element.health -= player.weapon.dmg;
                bullet.enemiesHit += 1;
                if (bullet.enemiesHit >= player.weapon.maxEnemiesHit) {
                    bullets.splice(bullets.indexOf(bullet), 1)
                }
                break;
            }
        }

        if (element.health <= 0) {
            enemies.splice(enemies.indexOf(element), 1)
            continue
        }

        if (Collison(player.data, element.data)) {
            element.Collided = true
            element.collisonCount += 1

            if (Math.round(Math.random()*500) === 1) {
                player.data[0] = element.data[0]
                player.data[1] = element.data[1]
                player.ailments.grabbed.inflict = true
                player.ailments.grabbed.duration = globalCounter + 30
            }

            if (Math.round(Math.random()*100) === 1) {
                player.ailments.slowed.inflict = true
                player.ailments.slowed.slowedBy = 0.5
                player.ailments.slowed.duration = globalCounter + 60
            }

            if (element.Collided && element.collisonCount >= 30) {
                player.health -= Math.round(Math.random() * 5)
                enemies.splice(enemies.indexOf(element), 1)
                continue
            }
        } else {
            element.collisonCount = 0
            element.Collided = false
        }
        if (bulletRequest) {
            if (player.weapon.ammo > 0) {
                for (let x = 0; x != 4; x++) {
                    spawnBullets(Math.floor(Math.random() * (enemies.length-1)))
                }
                player.weapon.ammo -= 1;
                bulletRequest = false;
            }
        }
        drawRect(...element.data)
    };
    for(const bullet of bullets) {
        if (!bullet) {continue;}
        drawRect(...bullet.data)
    }

    healthElement.textContent = `Health: ${player.health}`
    bulletElement.textContent = `Mosquitos: ${player.weapon.ammo} (req: ${bulletRequest})`
    waveElement.textContent = `Wave: ${waveDat.wave} (Enemies Till Next Wave: ${waveDat.enemiesToSpawn - waveDat.currentWave.spawnedEnemies})`

    if (player.health <= 0) {
        gameOverElement.style.display = "flex"
        return;
    }

    requestAnimationFrame(gameLoop)
}

requestAnimationFrame(gameLoop)
