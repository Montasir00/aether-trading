<?php

class PositionManager {
    private int $userId;

    public function __construct(int $userId) {
        $this->userId = $userId;
    }

    private function getPos(): string {
        global $conn;
        $stmt = $conn->prepare("SELECT bot_position FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row['bot_position'] ?? 'NONE';
    }

    private function setPos(string $pos): void {
        global $conn;
        $stmt = $conn->prepare("UPDATE users SET bot_position = ? WHERE id = ?");
        $stmt->bind_param("si", $pos, $this->userId);
        $stmt->execute();
    }

    public function canBuy(): bool {
        return $this->getPos() === 'NONE';
    }

    public function canSell(): bool {
        return $this->getPos() === 'LONG';
    }

    public function enterLong(): void {
        $this->setPos('LONG');
    }

    public function exitLong(): void {
        $this->setPos('NONE');
    }

    public function getPosition(): string {
        return $this->getPos();
    }
}
