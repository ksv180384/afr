import Phaser from 'phaser';
import backgroundUrl from './assets/alpine-valley-background-v1.png';

const cardColors = [0xe8e0ff, 0xffedbc, 0xdff7c4, 0xd7f5f0, 0xffe0ed, 0xdcecff];
const textColors = ['#5847a2', '#855f23', '#4d7632', '#28766f', '#98476d', '#35658d'];

class FallingTranslationsScene extends Phaser.Scene {
  constructor() {
    super('falling-translations');
    this.question = null;
    this.answerHandler = null;
    this.cards = [];
    this.selectedCard = null;
    this.wrongAttempts = 0;
    this.wrongMessage = null;
    this.ready = false;
    this.locked = false;
  }

  preload() {
    this.load.image('falling-translations-background', backgroundUrl);
  }

  create() {
    this.ready = true;
    this.scale.on('resize', () => this.draw());
    if (this.question) this.draw();
  }

  setQuestion(question) {
    this.question = question;
    if (this.ready) this.draw();
  }

  draw() {
    if (!this.ready || !this.question) return;
    this.tweens.killAll();
    this.tweens.resumeAll();
    this.children.removeAll(true);
    this.cards = [];
    this.selectedCard = null;
    this.wrongAttempts = 0;
    this.wrongMessage = null;
    this.locked = false;

    const { width, height } = this.scale;
    this.drawBackground(width, height);
    this.drawLanes(width, height);
    this.drawAmbientLeaves(width, height);
    this.drawWord(width, height);
    this.drawCards(width, height);
  }

  drawBackground(width, height) {
    this.add.image(width / 2, height / 2, 'falling-translations-background')
      .setDisplaySize(width, height)
      .setOrigin(0.5);
  }

  getLaneCount(width) {
    if (width < 520) return 3;
    if (width < 780) return 4;
    if (width < 1100) return 5;
    return 6;
  }

  drawLanes(width, height) {
    const laneCount = Math.max(5, this.getLaneCount(width));
    const usableWidth = Math.min(width * 0.94, 980);
    const laneWidth = usableWidth / laneCount;
    const startX = (width - usableWidth) / 2;
    const top = Math.max(108, height * 0.17);
    const bottom = Math.max(top + 220, height - 96);
    const graphics = this.add.graphics();

    for (let index = 0; index < laneCount; index++) {
      const x = startX + index * laneWidth + laneWidth * 0.12;
      const w = laneWidth * 0.76;
      graphics.fillStyle(0xffffff, 0.085);
      graphics.fillRoundedRect(x, top, w, bottom - top, 26);
      graphics.lineStyle(2, 0xffffff, 0.24);
      graphics.strokeRoundedRect(x, top, w, bottom - top, 26);
    }
  }

  drawAmbientLeaves(width, height) {
    const count = width < 680 ? 10 : 18;
    for (let index = 0; index < count; index++) {
      const leaf = this.add.ellipse(
        Phaser.Math.Between(12, width - 12),
        Phaser.Math.Between(Math.round(height * 0.18), Math.round(height * 0.82)),
        Phaser.Math.Between(5, 11),
        Phaser.Math.Between(9, 17),
        index % 3 === 0 ? 0xe8f88f : 0xffffff,
        0.42,
      ).setRotation(Phaser.Math.FloatBetween(-0.9, 0.9));

      this.tweens.add({
        targets: leaf,
        x: leaf.x + Phaser.Math.Between(-18, 18),
        y: leaf.y + Phaser.Math.Between(22, 58),
        rotation: leaf.rotation + Phaser.Math.FloatBetween(0.8, 1.8),
        alpha: 0.08,
        duration: Phaser.Math.Between(2600, 5200),
        yoyo: true,
        repeat: -1,
      });
    }
  }

  drawWord(width, height) {
    const wordY = height * (width < 680 ? 0.61 : 0.62);
    const fontSize = Math.min(82, Math.max(40, width / (width < 680 ? 8 : 13)));
    const maxWidth = Math.min(width * 0.84, 760);

    const word = this.add.text(width / 2, wordY, this.question.word.text, {
      fontFamily: 'Arial, sans-serif',
      fontSize: `${fontSize}px`,
      fontStyle: 'bold',
      color: '#19475f',
      stroke: '#ffffff',
      strokeThickness: 2,
      shadow: { offsetX: 0, offsetY: 5, color: '#2c7995', blur: 8, fill: true },
      align: 'center',
      wordWrap: { width: maxWidth },
    }).setOrigin(0.5).setDepth(6).setAlpha(0).setScale(0.82);

    const helper = this.add.text(width / 2, wordY + fontSize * 0.82, 'Выбери правильный перевод', {
      fontFamily: 'Arial, sans-serif',
      fontSize: width < 680 ? '14px' : '18px',
      fontStyle: 'bold',
      color: '#4e8298',
    }).setOrigin(0.5).setDepth(6).setAlpha(0);

    this.tweens.add({ targets: word, alpha: 1, scale: 1, duration: 420, ease: 'Back.Out', delay: 100 });
    this.tweens.add({ targets: helper, alpha: 1, y: helper.y + 5, duration: 300, delay: 220 });
  }

  drawCards(width, height) {
    const isMobile = width < 680;
    const usableWidth = Math.min(width * 0.94, 980);
    const fieldLeft = (width - usableWidth) / 2;
    const cardWidth = Math.min(isMobile ? 112 : 144, usableWidth * (isMobile ? 0.29 : 0.15));
    const cardHeight = cardWidth < 120 ? 44 : 50;
    const startY = -cardHeight - 12;
    const endY = height + cardHeight + 18;
    const fallDistance = endY - startY;
    const safeVerticalGap = cardHeight + 30;
    const collisionDelay = Math.ceil((this.question.duration_ms * safeVerticalGap) / fallDistance) + 180;
    const randomDelayMax = Math.min(2600, Math.round(this.question.duration_ms * 0.075));
    const minX = fieldLeft + cardWidth / 2 + 4;
    const maxX = fieldLeft + usableWidth - cardWidth / 2 - 4;
    const collisionWidth = cardWidth + 38;
    const placements = [];

    this.question.options.forEach((option, index) => {
      let bestPlacement = null;

      for (let attempt = 0; attempt < 32; attempt++) {
        const x = Phaser.Math.FloatBetween(minX, maxX);
        let delay = Phaser.Math.Between(40, Math.max(80, randomDelayMax));

        placements.forEach((placement) => {
          if (Math.abs(x - placement.x) < collisionWidth) {
            delay = Math.max(delay, placement.delay + collisionDelay);
          }
        });

        const candidate = { x, delay, score: delay + Phaser.Math.Between(0, 240) };
        if (!bestPlacement || candidate.score < bestPlacement.score) bestPlacement = candidate;
      }

      placements.push(bestPlacement);
      const targetX = Phaser.Math.Clamp(
        bestPlacement.x + Phaser.Math.FloatBetween(-10, 10),
        minX,
        maxX,
      );
      const card = this.createCard(bestPlacement.x, startY, cardWidth, option, index);
      this.cards.push(card);

      this.tweens.add({
        targets: card,
        x: targetX,
        y: endY,
        duration: this.question.duration_ms,
        ease: 'Linear',
        delay: bestPlacement.delay,
        onComplete: () => {
          if (option.id === this.question.word.id) this.submit(null, null);
        },
      });
    });
  }

  createCard(x, y, width, option, index) {
    const height = width < 120 ? 44 : 50;
    const color = cardColors[index % cardColors.length];
    const body = this.add.graphics();
    const text = this.add.text(0, 0, option.text, {
      fontFamily: 'Arial, sans-serif',
      fontSize: width < 120 ? '11px' : '14px',
      fontStyle: 'bold',
      color: textColors[index % textColors.length],
      align: 'center',
      wordWrap: { width: width - 20 },
      maxLines: 2,
    }).setOrigin(0.5);

    const card = this.add.container(x, y, [body, text])
      .setSize(width, height)
      .setInteractive({ useHandCursor: true })
      .setDepth(8);
    card.optionId = option.id;
    card.cloudColor = color;
    card.dismissed = false;
    card.body = body;
    card.paint = (fillColor, glowAlpha = 0.28) => {
      body.clear();
      body.fillStyle(0x376a79, 0.2);
      body.fillRoundedRect(-width / 2 + 3, -height / 2 + 7, width, height, height / 2);
      body.fillStyle(fillColor, glowAlpha);
      body.fillRoundedRect(-width / 2 - 5, -height / 2 - 4, width + 10, height + 10, height / 2 + 5);
      body.fillStyle(fillColor, 0.98);
      body.fillRoundedRect(-width / 2, -height / 2, width, height, height / 2);
      body.lineStyle(2, 0xffffff, 0.95);
      body.strokeRoundedRect(-width / 2, -height / 2, width, height, height / 2);
    };
    card.paint(color);

    card.on('pointerover', () => {
      if (!this.locked) this.tweens.add({ targets: card, scale: 1.055, duration: 100 });
    });
    card.on('pointerout', () => {
      if (!this.locked) this.tweens.add({ targets: card, scale: 1, duration: 100 });
    });
    card.on('pointerdown', () => this.selectOption(option.id, card));
    return card;
  }

  selectOption(optionId, card) {
    if (this.locked || !card?.visible || !card.input?.enabled) return;
    if (optionId === this.question.word.id) {
      this.submit(optionId, card);
      return;
    }

    this.rejectCard(card);
  }

  rejectCard(card) {
    card.dismissed = true;
    this.wrongAttempts++;
    this.tweens.getTweensOf(card).forEach((tween) => tween.pause());
    card.paint(0xff7f91, 0.72);
    this.dissolveCard(card, { cloudColor: 0xff667b, accentColor: 0xffd5db, particles: 24 });
    this.showWrongMessage();
  }

  showWrongMessage() {
    const { width, height } = this.scale;
    this.wrongMessage?.destroy();
    const message = this.add.text(width / 2, height * 0.43, 'НЕВЕРНО\nВыберите другой вариант', {
      fontFamily: 'Arial, sans-serif',
      fontSize: width < 680 ? '18px' : '23px',
      fontStyle: 'bold',
      color: '#c93f59',
      stroke: '#ffffff',
      strokeThickness: 5,
      align: 'center',
      lineSpacing: 6,
    }).setOrigin(0.5).setDepth(16).setScale(0.72).setAlpha(0);
    this.wrongMessage = message;

    this.tweens.add({
      targets: message,
      alpha: 1,
      scale: 1,
      duration: 180,
      ease: 'Back.Out',
      onComplete: () => {
        if (!message.active) return;
        this.tweens.add({
          targets: message,
          y: message.y - 16,
          alpha: 0,
          delay: 650,
          duration: 350,
          ease: 'Cubic.In',
          onComplete: () => {
            message.destroy();
            if (this.wrongMessage === message) this.wrongMessage = null;
          },
        });
      },
    });
  }

  submit(optionId, card) {
    if (this.locked) return;
    this.locked = true;
    this.selectedCard = card;
    this.tweens.pauseAll();
    if (card) card.paint(0xfff1ad, 0.5);
    this.answerHandler?.(optionId, this.wrongAttempts);
  }

  showResult({ correct, correctOptionId, points, bonuses = {} }) {
    const { width, height } = this.scale;
    this.tweens.resumeAll();
    this.cards.forEach((card) => {
      this.tweens.getTweensOf(card).forEach((tween) => tween.pause());
      card.disableInteractive();
    });

    if (correct && this.selectedCard) {
      this.selectedCard.paint(0xc9f5a9, 0.9);
      this.dissolveCard(this.selectedCard, { cloudColor: 0xbff39f, accentColor: 0xffffff, particles: 30 });

      const remainingCards = Phaser.Utils.Array.Shuffle(
        this.cards.filter((card) => card !== this.selectedCard && !card.dismissed && card.visible && card.alpha > 0),
      );
      remainingCards.forEach((card, index) => {
        const spread = remainingCards.length > 1 ? index / (remainingCards.length - 1) : 0;
        const delay = Math.round(80 + spread * 260 + Phaser.Math.Between(-35, 35));
        this.dissolveCard(card, {
          cloudColor: card.cloudColor,
          accentColor: 0xffffff,
          delay: Math.max(50, delay),
          particles: 14,
        });
      });
    } else {
      this.cards.forEach((card) => {
        const isCorrect = card.optionId === correctOptionId;
        card.paint(isCorrect ? 0xc9f5a9 : 0xffcad4, isCorrect ? 0.9 : 0.08);
        card.setAlpha(isCorrect ? 1 : 0.25);
      });
    }

    const popup = this.add.text(width / 2, height * 0.47, correct ? `+${points}` : 'Время вышло!', {
      fontFamily: 'Arial, sans-serif',
      fontSize: correct ? '42px' : '24px',
      fontStyle: 'bold',
      color: correct ? '#31905b' : '#8b526c',
      stroke: '#ffffff',
      strokeThickness: 5,
    }).setOrigin(0.5).setScale(0.65).setAlpha(0).setDepth(15);

    this.tweens.add({ targets: popup, alpha: 1, scale: 1, duration: 250, ease: 'Back.Out' });

    if (correct && ((bonuses.speed_bonus ?? 0) > 0 || (bonuses.combo_bonus ?? 0) > 0)) {
      const details = [
        (bonuses.speed_bonus ?? 0) > 0 ? `скорость +${bonuses.speed_bonus}` : '',
        (bonuses.combo_bonus ?? 0) > 0 ? `комбо +${bonuses.combo_bonus}` : '',
      ].filter(Boolean).join('  •  ');
      const bonusPopup = this.add.text(width / 2, height * 0.47 + 48, details, {
        fontFamily: 'Arial, sans-serif',
        fontSize: width < 680 ? '13px' : '16px',
        fontStyle: 'bold',
        color: '#397d68',
        stroke: '#ffffff',
        strokeThickness: 4,
      }).setOrigin(0.5).setAlpha(0).setDepth(15);
      this.tweens.add({ targets: bonusPopup, alpha: 1, y: bonusPopup.y - 5, duration: 280, delay: 100 });
    }
  }

  dissolveCard(card, {
    cloudColor = card.cloudColor ?? 0xdff7c4,
    accentColor = 0xc9f5a9,
    delay = 0,
    particles = 26,
  } = {}) {
    card.disableInteractive();

    this.time.delayedCall(delay, () => {
      if (!card.active || !card.visible) return;
      const x = card.x;
      const y = card.y;
      const flash = this.add.circle(x, y, 18, 0xffffff, 0.86).setDepth(14).setScale(0.35);

      this.tweens.add({
        targets: card,
        alpha: 0,
        scaleX: 1.48,
        scaleY: 0.66,
        duration: 390,
        ease: 'Cubic.Out',
        onComplete: () => card.setVisible(false),
      });
      this.tweens.add({
        targets: flash,
        scale: 3.4,
        alpha: 0,
        duration: 390,
        ease: 'Cubic.Out',
        onComplete: () => flash.destroy(),
      });

      for (let index = 0; index < particles; index++) {
        const angle = Phaser.Math.FloatBetween(0, Math.PI * 2);
        const distance = Phaser.Math.Between(24, 96);
        const size = Phaser.Math.Between(10, 28);
        const color = index % 4 === 0 ? 0xffffff : (index % 3 === 0 ? accentColor : cloudColor);
        const blob = this.add.ellipse(x, y, size * 1.25, size, color, Phaser.Math.FloatBetween(0.6, 0.96))
          .setDepth(13)
          .setScale(0.18)
          .setRotation(angle);

        this.tweens.add({
          targets: blob,
          x: x + Math.cos(angle) * distance,
          y: y + Math.sin(angle) * distance * 0.62 - Phaser.Math.Between(3, 20),
          scaleX: Phaser.Math.FloatBetween(0.9, 1.6),
          scaleY: Phaser.Math.FloatBetween(0.7, 1.3),
          alpha: 0,
          rotation: angle + Phaser.Math.FloatBetween(-0.8, 0.8),
          duration: Phaser.Math.Between(380, 540),
          delay: Phaser.Math.Between(0, 80),
          ease: 'Cubic.Out',
          onComplete: () => blob.destroy(),
        });
      }
    });
  }

  setPaused(paused) {
    paused ? this.tweens.pauseAll() : this.tweens.resumeAll();
    this.input.enabled = !paused;
  }
}

export function mountFallingTranslations(element, { onAnswer }) {
  const scene = new FallingTranslationsScene();
  scene.answerHandler = onAnswer;

  const game = new Phaser.Game({
    type: Phaser.AUTO,
    parent: element,
    transparent: true,
    scale: { mode: Phaser.Scale.RESIZE, width: '100%', height: '100%', autoCenter: Phaser.Scale.CENTER_BOTH },
    render: { antialias: true, pixelArt: false, powerPreference: 'high-performance' },
    scene,
  });

  return {
    setQuestion: (question) => scene.setQuestion(question),
    showResult: (result) => scene.showResult(result),
    pause: (paused) => scene.setPaused(paused),
    destroy: () => game.destroy(true),
  };
}
