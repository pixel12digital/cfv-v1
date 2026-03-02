-- Seed 007: Categorias padrão de aula prática
-- Data: 2026-03-02
-- Categorias baseadas no DETRAN/CONTRAN

-- Limpar dados existentes (se houver)
DELETE FROM `lesson_categories`;

-- Inserir categorias padrão
INSERT INTO `lesson_categories` (`code`, `name`, `description`, `order`, `is_active`) VALUES
('A', 'Moto', 'Categoria A - Veículos motorizados de duas ou três rodas (motos, motonetas, triciclos)', 1, 1),
('B', 'Carro', 'Categoria B - Veículos automotores de quatro rodas (carros de passeio, utilitários)', 2, 1),
('C', 'Caminhão', 'Categoria C - Veículos de carga acima de 3.500kg (caminhões)', 3, 1),
('D', 'Ônibus', 'Categoria D - Veículos de transporte de passageiros acima de 8 lugares (ônibus, vans)', 4, 1),
('E', 'Carreta', 'Categoria E - Veículos com reboque ou semirreboque (carretas, treminhões)', 5, 1),
('AB', 'Moto + Carro', 'Categorias A e B combinadas', 6, 1),
('ACC', 'Adição C', 'Adição de categoria C (já possui A ou B)', 7, 1),
('ACD', 'Adição D', 'Adição de categoria D (já possui A, B ou C)', 8, 1),
('ACE', 'Adição E', 'Adição de categoria E (já possui A, B, C ou D)', 9, 1);

-- Resetar auto_increment
ALTER TABLE `lesson_categories` AUTO_INCREMENT = 1;
